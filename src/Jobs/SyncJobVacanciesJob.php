<?php

namespace Nawasara\JobVacancy\Jobs;

use Illuminate\Support\Carbon;
use Nawasara\JobVacancy\Exceptions\JobVacancyException;
use Nawasara\JobVacancy\Exceptions\UpstreamException;
use Nawasara\JobVacancy\Models\JobVacancy;
use Nawasara\JobVacancy\Services\JobVacancyClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

/**
 * Pull job — fetch job vacancies from the upstream service into the local DB
 * snapshot.
 *
 * Follows the AbstractSyncJob pattern: tracking row in nawasara_sync_jobs,
 * built-in retry/backoff, queue routing `job_vacancy_sync` when scheduled
 * (falls back to the `default` queue if not yet operational).
 *
 * The old snapshot is NOT touched on failure — API/UI keep serving the last
 * successful data.
 */
class SyncJobVacanciesJob extends AbstractSyncJob
{
    protected function service(): string
    {
        return 'job-vacancy';
    }

    protected function action(): string
    {
        return 'sync_job_vacancies';
    }

    protected function targetType(): ?string
    {
        return 'JobVacancy.JobVacancy';
    }

    protected function targetId(): ?string
    {
        return null;
    }

    protected function execute(): array
    {
        $client = JobVacancyClient::fromVault();

        if ($client === null) {
            throw new \RuntimeException('Vault group job-vacancy is not configured (base_url / api_token).');
        }

        try {
            $items = $client->allItems();
        } catch (JobVacancyException|UpstreamException $e) {
            throw new \RuntimeException('Sync failed: '.$e->getMessage(), 0, $e);
        }

        $now = now();

        foreach ($items as $item) {
            $sourceId = (string) ($item['id'] ?? '');

            if ($sourceId === '') {
                continue;
            }

            JobVacancy::updateOrCreate(
                ['source_id' => $sourceId],
                $this->columnMap($item, $now),
            );
        }

        $seenIds = collect($items)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => $id !== '')
            ->all();

        if ($seenIds !== []) {
            // Rows no longer returned by the upstream → deactivate (soft archive).
            JobVacancy::query()
                ->where('is_active', true)
                ->whereNotIn('source_id', $seenIds)
                ->update(['is_active' => false, 'is_expired' => true, 'synced_at' => $now]);
        }

        return ['total' => count($seenIds)];
    }

    /**
     * Map an upstream item → snapshot columns. Only columns safe to store are
     * kept; internal upstream fields are left out.
     */
    protected function columnMap(array $item, Carbon $now): array
    {
        return [
            'slug'                => (string) ($item['slug'] ?? ''),
            'job_title'           => (string) ($item['nama_pekerjaan'] ?? ''),
            'job_description'     => $item['deskripsi_pekerjaan'] ?? null,
            'company_name'        => (string) ($item['nama_perusahaan'] ?? ''),
            'company_description' => $item['deskripsi_perusahaan'] ?? null,
            'company_url'         => $item['url_perusahaan'] ?? null,
            'company_address'     => $item['alamat_perusahaan'] ?? null,
            'location'            => $item['lokasi'] ?? null,
            'salary'              => $item['gaji'] ?? null,
            'type'                => $item['tipe'] ?? null,
            'category'            => $item['kategory'] ?? null,
            'apply_url'           => $item['apply'] ?? null,
            'requirements'        => $item['persyaratan_kualifikasi'] ?? null,
            'expires_at'          => $this->nullableDate($item['tgl_berakhir'] ?? null),
            'is_expired'          => (bool) ($item['is_expired'] ?? false),
            'is_active'           => (bool) ($item['actived'] ?? true),
            'extern_created_at'   => $this->nullableDate($item['created_at'] ?? null),
            'extern_updated_at'   => $this->nullableDate($item['updated_at'] ?? null),
            'synced_at'           => $now,
        ];
    }

    protected function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}