<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Nawasara\JobVacancy\Jobs\SyncJobVacanciesJob;
use Nawasara\JobVacancy\Models\JobVacancy;
use Nawasara\Vault\Facades\Vault;
use Tests\TestCase;

class SyncJobVacanciesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Vault::shouldReceive('get')
            ->andReturnNull()
            ->byDefault();
    }

    private function fakeVaultCredentials(): void
    {
        Vault::shouldReceive('get')
            ->andReturnUsing(function (string $group, string $key, ?string $instance = null) {
                return match ($key) {
                    'base_url'  => 'https://upstream.test/api',
                    'api_token' => 'secret',
                    default     => null,
                };
            });
    }

    private function fakeItems(array $items): void
    {
        Http::fake([
            '*/v1/loker/lokers*' => Http::response([
                'data' => $items,
                'meta' => ['total' => count($items)],
            ]),
        ]);
    }

    public function test_command_fails_without_vault_configuration(): void
    {
        $this->artisan('job-vacancy:sync')
            ->assertExitCode(1);
    }

    public function test_command_dispatches_sync_job_to_queue(): void
    {
        $this->fakeVaultCredentials();

        Bus::fake();

        $this->artisan('job-vacancy:sync')
            ->assertExitCode(0);

        Bus::assertDispatched(SyncJobVacanciesJob::class);
    }

    public function test_command_sync_option_runs_inline(): void
    {
        $this->fakeVaultCredentials();

        $this->fakeItems([
            [
                'id'              => 'uuid-1',
                'slug'            => 'backend-engineer',
                'nama_pekerjaan'  => 'Backend Engineer',
                'nama_perusahaan' => 'PT Contoh',
                'lokasi'          => 'Jakarta',
                'tipe'            => 'Full-time',
                'kategory'        => 'Technology',
                'actived'         => true,
                'is_expired'      => false,
                'created_at'      => '2026-01-01T00:00:00Z',
                'updated_at'      => '2026-01-02T00:00:00Z',
            ],
        ]);

        $this->artisan('job-vacancy:sync', ['--sync' => true])
            ->assertExitCode(0);

        $jobVacancy = JobVacancy::query()->where('source_id', 'uuid-1')->first();

        $this->assertNotNull($jobVacancy);
        $this->assertSame('backend-engineer', $jobVacancy->slug);
        $this->assertSame('Backend Engineer', $jobVacancy->job_title);
        $this->assertNotNull($jobVacancy->synced_at);
    }
}