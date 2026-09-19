<?php

namespace Nawasara\JobVacancy\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Nawasara\JobVacancy\Exceptions\JobVacancyException;
use Nawasara\JobVacancy\Exceptions\UpstreamException;
use Nawasara\Vault\Facades\Vault;

/**
 * HTTP transport to the upstream service platform (Vault group `job-vacancy`).
 * The only layer that talks to the upstream server — used by `SyncJobVacanciesJob`
 * (queue job; `job-vacancy:sync --sync` for inline) and the Vault "Test" button.
 *
 * API controllers and Livewire NEVER touch this class: they read the local DB
 * snapshot (`nawasara_job_vacancies`) filled by the periodic sync.
 */
class JobVacancyClient
{
    public function __construct(
        private string $baseUrl = '',
        private string $apiToken = '',
        private int $timeout = 15,
        private int $retryTimes = 1,
        private int $retrySleepMs = 100,
    ) {}

    /**
     * Build a client from Vault (group 'job-vacancy').
     * null when Vault is not configured or fields are empty.
     */
    public static function fromVault(): ?static
    {
        $group    = (string) config('nawasara-job-vacancy.vault.group', 'job-vacancy');
        $baseUrl  = (string) Vault::get($group, 'base_url');
        $token    = (string) Vault::get($group, 'api_token');

        if ($baseUrl === '' || $token === '') {
            return null;
        }

        return new static(
            baseUrl:      $baseUrl,
            apiToken:     $token,
            timeout:      (int) config('nawasara-job-vacancy.http.timeout', 15),
            retryTimes:   (int) config('nawasara-job-vacancy.http.retry_times', 1),
            retrySleepMs: (int) config('nawasara-job-vacancy.http.retry_sleep_ms', 100),
        );
    }

    /**
     * Pull all pages of GET {base}/api/v1/{service}/{resource} → flat item list.
     *
     * Stops early when a page is shorter than per_page (last page). Throws on
     * HTTP error/timeout — the caller decides whether the snapshot should fail.
     *
     * @return array<int, array> Raw job vacancy items from the upstream.
     *
     * @throws \Nawasara\JobVacancy\Exceptions\UpstreamException
     * @throws \Nawasara\JobVacancy\Exceptions\JobVacancyException
     */
    public function allItems(): array
    {
        $perPage  = (int) config('nawasara-job-vacancy.job_vacancy.per_page', 100);
        $maxPages = (int) config('nawasara-job-vacancy.job_vacancy.max_pages', 3);
        $items    = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            [$batch, $meta] = $this->fetchPage($perPage, $page);

            $items = array_merge($items, $batch);

            $currentPage = (int) ($meta['current_page'] ?? $page);
            $perPageMeta = (int) ($meta['per_page'] ?? $perPage);
            $total       = (int) ($meta['total'] ?? 0);
            $lastPage    = (int) ($meta['last_page'] ?? ($perPageMeta > 0 ? (int) ceil($total / $perPageMeta) : PHP_INT_MAX));

            if ($batch === [] || count($batch) < $perPage || $currentPage >= $lastPage) {
                break;
            }
        }

        return $items;
    }

    /**
     * Fetch a single page of job vacancies.
     *
     * @return array<int, array> Items on that page.
     *
     * @throws \Nawasara\JobVacancy\Exceptions\UpstreamException
     * @throws \Nawasara\JobVacancy\Exceptions\JobVacancyException
     */
    public function itemsPage(int $perPage = 100, int $page = 1): array
    {
        [$data] = $this->fetchPage($perPage, $page);

        return $data;
    }

    /**
     * @return array{0: array<int, array>, 1: array<string, mixed>}
     */
    private function fetchPage(int $perPage, int $page): array
    {
        try {
            $response = $this->request()
                ->get($this->endpoint(), [
                    'per_page' => min($perPage, (int) config('nawasara-job-vacancy.job_vacancy.max_per_page', 100)),
                    'page'     => max(1, $page),
                ]);
        } catch (ConnectionException $e) {
            throw JobVacancyException::upstreamUnavailable('The job vacancy service is unreachable.', previous: $e);
        }

        if (! $response->successful()) {
            throw UpstreamException::fromResponse($response);
        }

        return [
            (array) $response->json('data', []),
            (array) $response->json('meta', []),
        ];
    }

    /**
     * Vault contract: connectivity test. Called by Vault via `app()->call(...)`
     * — the instance comes from the container (default constructor), so
     * credentials are re-read from Vault here (Cloudflare/Whm pattern).
     * MUST return ['success' => bool, 'message' => string].
     */
    public function testConnection(?string $instance = null): array
    {
        $group   = (string) config('nawasara-job-vacancy.vault.group', 'job-vacancy');
        $baseUrl = (string) Vault::get($group, 'base_url', $instance);
        $token   = (string) Vault::get($group, 'api_token', $instance);

        if ($baseUrl === '' || $token === '') {
            return ['success' => false, 'message' => 'Credentials incomplete (base_url / api_token).'];
        }

        $client = new static(baseUrl: $baseUrl, apiToken: $token);

        try {
            $response = $client->request()
                ->get($client->endpoint(), [
                    'per_page' => 1,
                ]);

            if ($response->successful()) {
                return ['success' => true,  'message' => 'Connection to the upstream service succeeded.'];
            }

            $status = $response->status();

            if (in_array($status, [401, 403], true)) {
                return ['success' => false, 'message' => 'Upstream token invalid (HTTP '.$status.').'];
            }

            return ['success' => false, 'message' => 'Upstream replied HTTP '.$status.'.'];
        } catch (ConnectionException $e) {
            return ['success' => false, 'message' => 'Unable to reach the upstream service: '.$e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unexpected error: '.$e->getMessage()];
        }
    }

    // ─── Internal ──────────────────────────────────────────────────────────

    /**
     * Upstream endpoint: server contract = {base}/api/{version}/{service}/{resource}.
     * base_url (Vault) holds the root HOST (e.g. https://jobs.example.com) —
     * the `/api` prefix is HARDCODED; version + service/resource from config.
     */
    private function endpoint(): string
    {
        $service  = (string) config('nawasara-job-vacancy.job_vacancy.service', 'loker');
        $version  = (string) config('nawasara-job-vacancy.job_vacancy.version', 'v1');
        $resource = (string) config('nawasara-job-vacancy.job_vacancy.resource', 'lokers');

        return rtrim($this->baseUrl, '/')."/api/{$version}/{$service}/{$resource}";
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->apiToken)
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleepMs, throw: false);
    }
}