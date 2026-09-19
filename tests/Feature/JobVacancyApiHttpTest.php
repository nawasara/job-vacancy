<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Nawasara\Api\Services\TokenManager;
use Nawasara\JobVacancy\Models\JobVacancy;
use Tests\TestCase;

/**
 * End-to-end tests over the full HTTP path (/api/v1/job-vacancy) with real
 * API tokens (`TokenManager::create`). M6-B: closes the "auth/scope only
 * smoke-tested manually" gap — 401/403/200/404 regressions are now caught
 * automatically. Includes verification of the `job-vacancy-api` rate limiter.
 */
class JobVacancyApiHttpTest extends TestCase
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

    private function issueToken(array $scopes): string
    {
        ['plaintext' => $plaintext] = app(TokenManager::class)->create('M6 e2e', $scopes);

        return $plaintext;
    }

    private function authorizedGet(string $uri, string $plaintext): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$plaintext)->getJson($uri);
    }

    public function test_401_without_token(): void
    {
        $this->getJson('/api/v1/job-vacancy/job-vacancies')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'missing_token');
    }

    public function test_401_with_invalid_token(): void
    {
        $this->withHeader('Authorization', 'Bearer nws_'.Str::random(40))
            ->getJson('/api/v1/job-vacancy/job-vacancies')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_token');
    }

    public function test_403_insufficient_scope(): void
    {
        $token = $this->issueToken(['cctv.camera.read']);

        $this->authorizedGet('/api/v1/job-vacancy/job-vacancies', $token)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'insufficient_scope');
    }

    public function test_200_list_compact_and_meta_over_http(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(['slug' => 'sales-cashier', 'job_title' => 'Sales Cashier']);
        $token = $this->issueToken(['job.vacancy.read']);

        $response = $this->authorizedGet('/api/v1/job-vacancy/job-vacancies', $token);

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');

        $keys = array_keys($response->json('data.0'));
        $this->assertSame(
            ['slug', 'job_title', 'company', 'location', 'type', 'category', 'salary', 'is_expired', 'expires_at'],
            $keys,
        );
    }

    public function test_200_search_by_description_over_http(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(overrides: ['slug' => 'sales-cashier', 'job_title' => 'Sales Cashier', 'job_description' => 'Serve customers']);
        $token = $this->issueToken(['job.vacancy.read']);

        $this->authorizedGet('/api/v1/job-vacancy/job-vacancies?q=Record%20daily%20store%20finance', $token)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'finance-admin');
    }

    public function test_200_detail_over_http(): void
    {
        $jobVacancy = $this->makeJobVacancy();
        $token = $this->issueToken(['job.vacancy.read']);

        $this->authorizedGet('/api/v1/job-vacancy/job-vacancies/'.$jobVacancy->slug, $token)
            ->assertOk()
            ->assertJsonPath('data.slug', $jobVacancy->slug)
            ->assertJsonPath('data.apply_url', 'https://bit.ly/apply')
            ->assertJsonPath('data.job_description', 'Record daily store finance');
    }

    public function test_404_unknown_slug_over_http(): void
    {
        $token = $this->issueToken(['job.vacancy.read']);

        $this->authorizedGet('/api/v1/job-vacancy/job-vacancies/does-not-exist', $token)
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    public function test_rate_limiter_job_vacancy_api_is_registered(): void
    {
        $this->assertIsCallable(RateLimiter::limiter('job-vacancy-api'));

        $this->assertTrue(RateLimiter::attempt('job-vacancy:1:127.0.0.1', 60, fn () => true));
    }

    public function test_429_when_rate_limit_exceeded(): void
    {
        config(['nawasara-job-vacancy.job_vacancy.api.rate_limit_per_minute' => 2]);
        $token = $this->issueToken(['job.vacancy.read']);

        $this->authorizedGet('/api/v1/job-vacancy/job-vacancies', $token)->assertOk();
        $this->authorizedGet('/api/v1/job-vacancy/job-vacancies', $token)->assertOk();
        $this->authorizedGet('/api/v1/job-vacancy/job-vacancies', $token)->assertStatus(429);
    }
}