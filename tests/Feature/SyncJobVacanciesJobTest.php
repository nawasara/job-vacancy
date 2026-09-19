<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Nawasara\JobVacancy\Jobs\SyncJobVacanciesJob;
use Nawasara\JobVacancy\Models\JobVacancy;
use Nawasara\Sync\Models\SyncJob;
use Nawasara\Vault\Facades\Vault;
use Tests\TestCase;

class SyncJobVacanciesJobTest extends TestCase
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

    public function test_job_upserts_items_into_snapshot(): void
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
                'tgl_berakhir'    => '2026-12-31T00:00:00Z',
                'created_at'      => '2026-01-01T00:00:00Z',
                'updated_at'      => '2026-01-02T00:00:00Z',
            ],
        ]);

        SyncJobVacanciesJob::dispatch(triggerSource: 'scheduled');

        $jobVacancy = JobVacancy::query()->where('source_id', 'uuid-1')->first();

        $this->assertNotNull($jobVacancy);
        $this->assertSame('backend-engineer', $jobVacancy->slug);
        $this->assertSame('Backend Engineer', $jobVacancy->job_title);
        $this->assertTrue($jobVacancy->is_active);
        $this->assertFalse($jobVacancy->is_expired);
        $this->assertSame('2026-12-31 00:00:00', $jobVacancy->expires_at->toDateTimeString());
        $this->assertNotNull($jobVacancy->synced_at);

        $tracker = SyncJob::query()
            ->where('service', 'job-vacancy')
            ->where('action', 'sync_job_vacancies')
            ->first();

        $this->assertNotNull($tracker);
        $this->assertSame('scheduled', $tracker->trigger_source);
        $this->assertSame(SyncJob::STATUS_SUCCESS, $tracker->status);
    }

    public function test_job_soft_archives_rows_no_longer_present(): void
    {
        $this->fakeVaultCredentials();

        JobVacancy::create([
            'source_id'   => 'gone-1',
            'slug'        => 'old',
            'job_title'   => 'Old Job',
            'company_name' => 'PT Lama',
            'is_active'   => true,
            'is_expired'  => false,
            'synced_at'   => now()->subDay(),
        ]);

        $this->fakeItems([
            [
                'id'             => 'uuid-1',
                'slug'           => 'new-job',
                'nama_pekerjaan' => 'New Job',
                'actived'        => true,
                'is_expired'     => false,
            ],
        ]);

        SyncJobVacanciesJob::dispatch();

        $gone = JobVacancy::query()->where('source_id', 'gone-1')->first();

        $this->assertFalse($gone->is_active);
        $this->assertTrue($gone->is_expired);
    }

    public function test_job_updates_existing_item_without_duplicate(): void
    {
        $this->fakeVaultCredentials();

        JobVacancy::create([
            'source_id'   => 'uuid-1',
            'slug'        => 'backend-engineer',
            'job_title'   => 'Backend Engineer',
            'company_name' => 'PT Contoh',
            'location'    => 'Jakarta',
            'type'        => 'Full-time',
            'is_active'   => true,
            'is_expired'  => false,
        ]);

        $this->fakeItems([
            [
                'id'             => 'uuid-1',
                'slug'           => 'backend-engineer',
                'nama_pekerjaan' => 'Senior Backend Engineer',
                'nama_perusahaan' => 'PT Contoh Baru',
                'lokasi'         => 'Bandung',
                'tipe'           => 'Hybrid',
                'actived'        => true,
                'is_expired'     => false,
            ],
        ]);

        SyncJobVacanciesJob::dispatch();

        $this->assertDatabaseCount('nawasara_job_vacancies', 1);
        $this->assertDatabaseHas('nawasara_job_vacancies', [
            'source_id'    => 'uuid-1',
            'job_title'    => 'Senior Backend Engineer',
            'company_name' => 'PT Contoh Baru',
            'location'     => 'Bandung',
            'type'         => 'Hybrid',
        ]);
    }

    public function test_job_paginates_through_all_items(): void
    {
        $this->fakeVaultCredentials();

        Http::fake([
            '*/v1/loker/lokers*' => function ($request) {
                $page = (int) $request['page'];

                return Http::response([
                    'data' => [
                        $page === 1
                            ? ['id' => 'p1', 'slug' => 'first', 'nama_pekerjaan' => 'First Job']
                            : ['id' => 'p2', 'slug' => 'second', 'nama_pekerjaan' => 'Second Job'],
                    ],
                    'meta' => ['current_page' => $page, 'per_page' => 1, 'last_page' => 2, 'total' => 2],
                ]);
            },
        ]);

        config(['nawasara-job-vacancy.job_vacancy.per_page' => 1]);
        config(['nawasara-job-vacancy.job_vacancy.max_pages' => 5]);

        SyncJobVacanciesJob::dispatch();

        $this->assertDatabaseHas('nawasara_job_vacancies', ['source_id' => 'p1']);
        $this->assertDatabaseHas('nawasara_job_vacancies', ['source_id' => 'p2']);
    }

    public function test_job_defaults_missing_fields(): void
    {
        $this->fakeVaultCredentials();

        $this->fakeItems([
            [
                'id'   => 'uuid-5',
                'slug' => 'minimal-item',
            ],
        ]);

        SyncJobVacanciesJob::dispatch();

        $this->assertDatabaseHas('nawasara_job_vacancies', [
            'source_id' => 'uuid-5',
            'slug'      => 'minimal-item',
            'is_active' => true,
        ]);
    }

    public function test_job_failure_does_not_touch_snapshot(): void
    {
        $this->fakeVaultCredentials();

        JobVacancy::create([
            'source_id'   => 'existing-1',
            'slug'        => 'still-here',
            'job_title'   => 'Existing Job',
            'company_name' => 'PT Tetap',
            'is_active'   => true,
            'is_expired'  => false,
            'synced_at'   => now()->subDay(),
        ]);

        Http::fake([
            '*/v1/loker/lokers*' => Http::response(['message' => 'Server Error'], 500),
        ]);

        Event::fake([JobFailed::class]);

        try {
            SyncJobVacanciesJob::dispatch(triggerSource: 'scheduled');
            $this->fail('Expected SyncJobVacanciesJob to throw on upstream failure');
        } catch (\Throwable) {
            // Failure handled by AbstractSyncJob lifecycle (retry → failed).
        }

        $existing = JobVacancy::query()->where('source_id', 'existing-1')->first();

        $this->assertTrue($existing->is_active);
        $this->assertFalse($existing->is_expired);

        $tracker = SyncJob::query()
            ->where('service', 'job-vacancy')
            ->where('action', 'sync_job_vacancies')
            ->first();

        $this->assertNotNull($tracker);
        $this->assertSame(SyncJob::STATUS_FAILED, $tracker->status);
    }
}