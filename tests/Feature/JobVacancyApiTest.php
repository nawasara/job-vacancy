<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Nawasara\JobVacancy\Http\Api\JobVacancyController;
use Nawasara\JobVacancy\Models\JobVacancy;
use Tests\TestCase;

/**
 * Tests the /job-vacancies endpoint — invoked directly via the controller
 * (repo style: without HTTP/token).
 * Endpoint optimized for mobile apps: compact list (JobVacancyListResource),
 * stable pagination (id tie-breaker), only active vacancies (scopeActive).
 */
class JobVacancyApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeJobVacancy(array $overrides = []): JobVacancy
    {
        return JobVacancy::create(array_merge([
            'source_id'          => (string) Str::uuid(),
            'slug'               => 'finance-admin',
            'job_title'          => 'Finance Admin',
            'job_description'    => 'Record daily store finance',
            'company_name'       => 'Example Bakery',
            'company_description' => 'Cake producer',
            'company_url'        => 'https://bit.ly/profile',
            'company_address'    => 'Jl. Gajah Mada No.22',
            'location'           => 'Jakarta',
            'salary'             => 'Rp 3.500.000',
            'type'               => 'Full time',
            'category'           => 'General',
            'apply_url'          => 'https://bit.ly/apply',
            'requirements'       => [
                'time'   => 1,
                'blocks' => [
                    ['type' => 'header', 'data' => ['level' => 3, 'text' => 'Qualifications']],
                ],
            ],
            'expires_at'       => '2026-10-01',
            'is_expired'       => false,
            'is_active'        => true,
            'extern_created_at' => now()->subDay(),
            'extern_updated_at' => now(),
        ], $overrides));
    }

    private function index(array $query = []): array
    {
        $request  = Request::create('/api/v1/job-vacancy/job-vacancies', 'GET', $query);
        $response = (new JobVacancyController())->index($request);

        return $response->getData(true);
    }

    private function show(string $slug): array
    {
        $response = (new JobVacancyController())->show($slug);

        return [
            'status' => $response->status(),
            'body'   => $response->getData(true),
        ];
    }

    public function test_index_returns_compact_list_and_meta_envelope(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(['slug' => 'sales-cashier', 'job_title' => 'Sales Cashier']);

        $body = $this->index();

        $this->assertCount(2, $body['data']);
        $this->assertSame(
            ['slug', 'job_title', 'company', 'location', 'type', 'category', 'salary', 'is_expired', 'expires_at'],
            array_keys($body['data'][0]),
        );
        $this->assertArrayNotHasKey('job_description', $body['data'][0]);
        $this->assertArrayNotHasKey('requirements', $body['data'][0]);
        $this->assertArrayNotHasKey('apply_url', $body['data'][0]);
        $this->assertArrayNotHasKey('source_id', $body['data'][0]);
        $this->assertSame(2, $body['meta']['total']);
        $this->assertArrayHasKey('per_page', $body['meta']);
        $this->assertArrayHasKey('current_page', $body['meta']);
        $this->assertArrayHasKey('last_page', $body['meta']);
    }

    public function test_index_searches_by_job_title(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(['slug' => 'sales-cashier', 'job_title' => 'Sales Cashier', 'job_description' => 'Serve customers']);

        $body = $this->index(['q' => 'Finance Admin']);

        $this->assertCount(1, $body['data']);
        $this->assertSame('finance-admin', $body['data'][0]['slug']);
    }

    public function test_index_searches_by_job_description(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(['slug' => 'sales-cashier', 'job_title' => 'Sales Cashier', 'job_description' => 'Cashier serves customers']);

        $body = $this->index(['q' => 'Record daily store finance']);

        $this->assertCount(1, $body['data']);
        $this->assertSame('finance-admin', $body['data'][0]['slug']);
    }

    public function test_index_searches_by_company_name(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(['slug' => 'sales-cashier', 'job_title' => 'Sales Cashier', 'company_name' => 'Delicious Cakes']);

        $body = $this->index(['q' => 'Delicious Cakes']);

        $this->assertCount(1, $body['data']);
        $this->assertSame('sales-cashier', $body['data'][0]['slug']);
    }

    public function test_index_filters_by_location(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(['slug' => 'chef-east-java', 'job_title' => 'Chef', 'location' => 'Surabaya']);

        $body = $this->index(['location' => 'Jakarta']);

        $this->assertCount(1, $body['data']);
        $this->assertSame('finance-admin', $body['data'][0]['slug']);
    }

    public function test_index_filters_by_type(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(['slug' => 'cashier-part', 'job_title' => 'Cashier', 'type' => 'Part time']);

        $body = $this->index(['type' => 'Full time']);

        $this->assertCount(1, $body['data']);
        $this->assertSame('finance-admin', $body['data'][0]['slug']);
    }

    public function test_index_filters_by_category(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(['slug' => 'programmer-it', 'job_title' => 'Programmer', 'category' => 'IT']);

        $body = $this->index(['category' => 'General']);

        $this->assertCount(1, $body['data']);
        $this->assertSame('finance-admin', $body['data'][0]['slug']);
    }

    public function test_index_excludes_expired_job_vacancies(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(['slug' => 'stale-vacancy', 'job_title' => 'Stale', 'is_expired' => true, 'expires_at' => '2020-01-01']);

        $body = $this->index();

        $this->assertCount(1, $body['data']);
        $this->assertSame('finance-admin', $body['data'][0]['slug']);
    }

    public function test_index_clamps_per_page_to_max(): void
    {
        $this->makeJobVacancy();

        $body = $this->index(['per_page' => '9999']);

        $this->assertSame(100, $body['meta']['per_page']);
    }

    public function test_index_clamps_per_page_to_min(): void
    {
        $this->makeJobVacancy();

        $body = $this->index(['per_page' => '0']);

        $this->assertSame(1, $body['meta']['per_page']);
    }

    public function test_index_page_ordering_is_stable_by_id(): void
    {
        $sameStamp = now()->subDay();

        $this->makeJobVacancy(['slug' => 'order-1', 'job_title' => 'Order One', 'extern_created_at' => $sameStamp]);
        $this->makeJobVacancy(['slug' => 'order-2', 'job_title' => 'Order Two', 'extern_created_at' => $sameStamp]);
        $this->makeJobVacancy(['slug' => 'order-3', 'job_title' => 'Order Three', 'extern_created_at' => $sameStamp]);

        $body = $this->index();

        $this->assertSame(['order-3', 'order-2', 'order-1'], array_column($body['data'], 'slug'));
    }

    public function test_show_returns_full_detail(): void
    {
        $jobVacancy = $this->makeJobVacancy();

        $result = $this->show($jobVacancy->slug);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Finance Admin', $result['body']['data']['job_title']);
        $this->assertSame('Record daily store finance', $result['body']['data']['job_description']);
        $this->assertSame('https://bit.ly/apply', $result['body']['data']['apply_url']);
        $this->assertArrayHasKey('requirements', $result['body']['data']);
        $this->assertArrayHasKey('created_at', $result['body']['data']);
        $this->assertArrayNotHasKey('source_id', $result['body']['data']);
        $this->assertArrayNotHasKey('is_active', $result['body']['data']);
    }

    public function test_show_404_for_unknown_slug(): void
    {
        $result = $this->show('does-not-exist');

        $this->assertSame(404, $result['status']);
        $this->assertSame('not_found', $result['body']['error']);
    }

    public function test_show_404_for_expired_job_vacancy(): void
    {
        $expired = $this->makeJobVacancy(['slug' => 'stale-vacancy', 'is_expired' => true, 'expires_at' => '2020-01-01']);

        $result = $this->show($expired->slug);

        $this->assertSame(404, $result['status']);
        $this->assertSame('not_found', $result['body']['error']);
    }
}