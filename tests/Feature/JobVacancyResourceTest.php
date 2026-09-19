<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Nawasara\JobVacancy\Http\Resources\JobVacancyListResource;
use Nawasara\JobVacancy\Http\Resources\JobVacancyResource;
use Nawasara\JobVacancy\Models\JobVacancy;
use Tests\TestCase;

class JobVacancyResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeJobVacancy(array $overrides = []): JobVacancy
    {
        return JobVacancy::create(array_merge([
            'source_id'           => (string) Str::uuid(),
            'slug'                => 'finance-admin',
            'job_title'           => 'Finance Admin',
            'job_description'     => '<script>alert(1)</script>Description',
            'company_name'        => 'Example Bakery',
            'company_description' => 'Cake producer',
            'company_url'         => 'https://bit.ly/profile',
            'company_address'     => 'Jl. Gajah Mada No.22',
            'location'            => 'Jakarta',
            'salary'              => 'Rp 3.500.000',
            'type'                => 'Full time',
            'category'            => 'General',
            'apply_url'           => 'https://bit.ly/apply',
            'requirements'        => [
                'time' => 1,
                'blocks' => [
                    ['type' => 'header', 'data' => ['level' => 3, 'text' => 'Qualifications']],
                    ['type' => 'list', 'data' => ['style' => 'ordered', 'items' => [
                        ['meta' => [], 'items' => [], 'content' => 'Max 30 years old.'],
                        ['meta' => [], 'items' => [], 'content' => '<b>Email:</b> resume@example.com'],
                    ]]],
                    ['type' => 'checklist', 'data' => ['items' => [['text' => 'x', 'checked' => true]]]],
                ],
            ],
            'expires_at'      => '2026-10-01',
            'is_expired'      => false,
            'is_active'       => true,
            'extern_created_at' => now()->subDay(),
            'extern_updated_at' => now(),
        ], $overrides));
    }

    public function test_resource_allow_list_hides_internal_columns(): void
    {
        $data = (new JobVacancyResource($this->makeJobVacancy()))->toArray(request());

        $this->assertSame('finance-admin', $data['slug']);
        $this->assertSame('Finance Admin', $data['job_title']);
        $this->assertSame('Example Bakery', $data['company_name']);
        $this->assertArrayNotHasKey('source_id', $data);
        $this->assertArrayNotHasKey('is_active', $data);
    }

    public function test_resource_normalizes_upstream_object_list_items(): void
    {
        $data = (new JobVacancyResource($this->makeJobVacancy()))->toArray(request());

        $blocks = $data['requirements']['blocks'];
        $list = collect($blocks)->firstWhere('type', 'list');

        $this->assertSame('Max 30 years old.', $list['data']['items'][0]);
        $this->assertSame('Email: resume@example.com', $list['data']['items'][1]);
        $this->assertSame('ordered', $list['data']['style']);
    }

    public function test_resource_whitelists_blocks_and_strips_html(): void
    {
        $data = (new JobVacancyResource($this->makeJobVacancy()))->toArray(request());

        $types = array_column($data['requirements']['blocks'], 'type');

        $this->assertContains('header', $types);
        $this->assertContains('list', $types);
        $this->assertNotContains('checklist', $types);
        $this->assertSame('Description', $data['job_description']);
    }

    public function test_resource_dates_are_iso8601(): void
    {
        $data = (new JobVacancyResource($this->makeJobVacancy()))->toArray(request());

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            (string) $data['created_at'],
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            (string) $data['updated_at'],
        );
    }

    public function test_list_resource_is_compact_allow_list(): void
    {
        $data = (new JobVacancyListResource($this->makeJobVacancy()))->toArray(request());

        $this->assertSame(['slug', 'job_title', 'company', 'location', 'type', 'category', 'salary', 'is_expired', 'expires_at'], array_keys($data));
        $this->assertSame('finance-admin', $data['slug']);
        $this->assertSame('Rp 3.500.000', $data['salary']);
        $this->assertSame(false, $data['is_expired']);
        $this->assertArrayNotHasKey('job_description', $data);
        $this->assertArrayNotHasKey('requirements', $data);
        $this->assertArrayNotHasKey('apply_url', $data);
        $this->assertArrayNotHasKey('source_id', $data);
        $this->assertArrayNotHasKey('is_active', $data);
    }

    public function test_list_resource_dates_are_iso8601(): void
    {
        $data = (new JobVacancyListResource($this->makeJobVacancy()))->toArray(request());

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            substr((string) $data['expires_at'], 0, 10),
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            (string) $data['expires_at'],
        );
    }

    public function test_resource_sanitizes_description(): void
    {
        $jobVacancy = $this->makeJobVacancy([
            'job_description' => '<b>Bold</b> <a href="javascript:alert(1)">link</a> '
                .'<script>evil()</script> tail',
        ]);

        $data = (new JobVacancyResource($jobVacancy))->toArray(request());

        $this->assertStringContainsString('<b>Bold</b>', $data['job_description']);
        $this->assertStringContainsString('<a', $data['job_description']);
        $this->assertStringContainsString('tail', $data['job_description']);
        $this->assertStringNotContainsString('href="javascript', $data['job_description']);
        $this->assertStringNotContainsString('script', $data['job_description']);
        $this->assertStringNotContainsString('evil', $data['job_description']);
    }
}