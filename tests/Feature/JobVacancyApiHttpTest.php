<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Nawasara\Api\Services\CitizenJwtVerifier;
use Nawasara\JobVacancy\Models\JobVacancy;
use Tests\TestCase;

/**
 * End-to-end tests over the full HTTP path (/api/v1/job-vacancy).
 *
 * The endpoint moved from `api.auth` (an `nws_` token, for trusted systems) to
 * `api.citizen` (a Keycloak JWT) on 20 September 2026, because the caller is
 * the SuperApps mobile app. An `nws_` token cannot serve phones: it leans on an
 * IP allow list, an Origin allow list, and the token staying secret, and none
 * of the three survives an APK that anyone can unpack.
 *
 * These tests stub the JWT verification rather than minting real Keycloak
 * tokens. What is under test is the route contract (401/200/404, the response
 * shape, the rate limiter), not the signature checking, which belongs to
 * nawasara/api and is tested there.
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

    /**
     * Act as a signed-in citizen.
     *
     * Swaps the JWT verifier for one that accepts a fixed token and returns the
     * claims a real Keycloak token would carry. The middleware, the route, the
     * rate limiter key and the controller all still run for real.
     */
    private function asCitizen(string $sub = 'warga-uji-0001'): self
    {
        $this->mock(CitizenJwtVerifier::class, function ($mock) use ($sub) {
            $mock->shouldReceive('verify')->andReturn(['sub' => $sub]);
        });

        return $this;
    }

    private function citizenGet(string $uri, string $sub = 'warga-uji-0001'): TestResponse
    {
        return $this->asCitizen($sub)
            ->withHeader('Authorization', 'Bearer jwt-palsu-untuk-uji')
            ->getJson($uri);
    }

    public function test_401_without_token(): void
    {
        $this->getJson('/api/v1/job-vacancy/job-vacancies')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'missing_token');
    }

    public function test_401_with_invalid_jwt(): void
    {
        $this->mock(CitizenJwtVerifier::class, function ($mock) {
            $mock->shouldReceive('verify')->andReturn(null);
        });

        $this->withHeader('Authorization', 'Bearer '.Str::random(40))
            ->getJson('/api/v1/job-vacancy/job-vacancies')
            ->assertStatus(401);
    }

    /**
     * An `nws_` system token no longer opens this endpoint.
     *
     * Kept as a test rather than deleted with the scope check: the endpoint used
     * to accept exactly this, and anything that silently starts accepting it
     * again has reopened the path meant for phones to trusted systems.
     */
    public function test_401_for_system_token(): void
    {
        $this->mock(CitizenJwtVerifier::class, function ($mock) {
            $mock->shouldReceive('verify')->andReturn(null);
        });

        $this->withHeader('Authorization', 'Bearer nws_'.Str::random(40))
            ->getJson('/api/v1/job-vacancy/job-vacancies')
            ->assertStatus(401);
    }

    public function test_200_list_compact_and_meta_over_http(): void
    {
        $this->makeJobVacancy();
        $this->makeJobVacancy(['slug' => 'sales-cashier', 'job_title' => 'Sales Cashier']);
        $response = $this->citizenGet('/api/v1/job-vacancy/job-vacancies');

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
        $this->citizenGet('/api/v1/job-vacancy/job-vacancies?q=Record%20daily%20store%20finance')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'finance-admin');
    }

    public function test_200_detail_over_http(): void
    {
        $jobVacancy = $this->makeJobVacancy();
        $this->citizenGet('/api/v1/job-vacancy/job-vacancies/'.$jobVacancy->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $jobVacancy->slug)
            ->assertJsonPath('data.apply_url', 'https://bit.ly/apply')
            ->assertJsonPath('data.job_description', 'Record daily store finance');
    }

    public function test_404_unknown_slug_over_http(): void
    {
        $this->citizenGet('/api/v1/job-vacancy/job-vacancies/does-not-exist')
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
        $this->citizenGet('/api/v1/job-vacancy/job-vacancies')->assertOk();
        $this->citizenGet('/api/v1/job-vacancy/job-vacancies')->assertOk();
        $this->citizenGet('/api/v1/job-vacancy/job-vacancies')->assertStatus(429);
    }
}