<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Nawasara\JobVacancy\Exceptions\JobVacancyException;
use Nawasara\JobVacancy\Exceptions\UpstreamException;
use Nawasara\JobVacancy\Services\JobVacancyClient;
use Nawasara\Vault\Facades\Vault;
use Tests\TestCase;

class JobVacancyClientTest extends TestCase
{
    private function client(): JobVacancyClient
    {
        return new JobVacancyClient('https://upstream.test/api', 'secret-token');
    }

    public function test_items_page_returns_data_array_on_success(): void
    {
        Http::fake([
            '*/v1/loker/lokers*' => Http::response([
                'data' => [
                    ['slug' => 'a', 'nama_pekerjaan' => 'Job A'],
                    ['slug' => 'b', 'nama_pekerjaan' => 'Job B'],
                ],
                'meta' => ['total' => 2],
            ]),
        ]);

        $result = $this->client()->itemsPage(50, 1);

        $this->assertCount(2, $result);
        $this->assertSame('a', $result[0]['slug']);
    }

    public function test_all_items_fetches_until_last_page(): void
    {
        Http::fake([
            '*/v1/loker/lokers*' => function ($request) {
                $page = (int) $request['page'];

                if ($page === 1) {
                    return Http::response([
                        'data' => [['slug' => 'a', 'id' => 'a-1']],
                        'meta' => ['current_page' => 1, 'per_page' => 1, 'total' => 2],
                    ]);
                }

                return Http::response([
                    'data' => [['slug' => 'b', 'id' => 'b-1']],
                    'meta' => ['current_page' => 2, 'per_page' => 1, 'total' => 2],
                ]);
            },
        ]);

        config(['nawasara-job-vacancy.job_vacancy.per_page' => 1]);
        config(['nawasara-job-vacancy.job_vacancy.max_pages' => 5]);

        $result = $this->client()->allItems();

        $this->assertCount(2, $result);
        $this->assertSame('b', $result[1]['slug']);
        Http::assertSentCount(2);
    }

    public function test_all_items_stops_after_max_pages(): void
    {
        Http::fake([
            '*/v1/loker/lokers*' => Http::response([
                'data' => [['slug' => 'a', 'id' => 'a-1']],
                'meta' => ['current_page' => 1, 'per_page' => 1, 'total' => 10],
            ]),
        ]);

        config(['nawasara-job-vacancy.job_vacancy.per_page' => 1]);
        config(['nawasara-job-vacancy.job_vacancy.max_pages' => 2]);

        $result = $this->client()->allItems();

        $this->assertCount(2, $result);
        Http::assertSentCount(2);
    }

    public function test_items_page_throws_upstream_auth_failed_on_401(): void
    {
        Http::fake([
            '*/v1/loker/lokers*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(UpstreamException::class);
        $this->expectExceptionMessage('The upstream API token is invalid');

        try {
            $this->client()->itemsPage(50, 1);
        } catch (UpstreamException $e) {
            $this->assertSame('upstream_auth_failed', $e->getErrorCode());
            throw $e;
        }
    }

    public function test_items_page_throws_upstream_not_found_on_404(): void
    {
        Http::fake([
            '*/v1/loker/lokers*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->expectException(UpstreamException::class);
        $this->expectExceptionMessage('The upstream resource was not found');

        try {
            $this->client()->itemsPage(50, 1);
        } catch (UpstreamException $e) {
            $this->assertSame('upstream_not_found', $e->getErrorCode());
            throw $e;
        }
    }

    public function test_items_page_throws_upstream_error_on_500(): void
    {
        Http::fake([
            '*/v1/loker/lokers*' => Http::response(['message' => 'Server Error'], 500),
        ]);

        $this->expectException(UpstreamException::class);
        $this->expectExceptionMessage('having issues');

        try {
            $this->client()->itemsPage(50, 1);
        } catch (UpstreamException $e) {
            $this->assertSame('upstream_error', $e->getErrorCode());
            throw $e;
        }
    }

    public function test_items_page_throws_upstream_unavailable_on_connection_error(): void
    {
        Http::fake([
            '*/v1/loker/lokers*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->expectException(JobVacancyException::class);

        $this->client()->itemsPage(50, 1);
    }

    public function test_test_connection_reports_success_on_200(): void
    {
        Vault::shouldReceive('get')
            ->andReturnUsing(function (string $group, string $key) {
                return match ($key) {
                    'base_url'  => 'https://upstream.test/api',
                    'api_token' => 'secret-token',
                    default     => null,
                };
            });

        Http::fake([
            '*/v1/loker/lokers*' => Http::response(['data' => []], 200),
        ]);

        $result = $this->client()->testConnection();

        $this->assertTrue($result['success']);
    }

    public function test_test_connection_reports_failure_on_401(): void
    {
        Vault::shouldReceive('get')
            ->andReturnUsing(function (string $group, string $key) {
                return match ($key) {
                    'base_url'  => 'https://upstream.test/api',
                    'api_token' => 'secret-token',
                    default     => null,
                };
            });

        Http::fake([
            '*/v1/loker/lokers*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $result = $this->client()->testConnection();

        $this->assertFalse($result['success']);
    }

    public function test_from_vault_returns_null_when_not_configured(): void
    {
        Vault::shouldReceive('get')
            ->andReturn(null);

        $this->assertNull(JobVacancyClient::fromVault());
    }
}