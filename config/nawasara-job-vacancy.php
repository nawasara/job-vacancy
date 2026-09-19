<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    |
    | Timeout and retry for calls to the upstream service platform.
    | Retry uses backoff with retry_sleep_ms (initial delay) and is used only
    | for sync transport (SyncJobVacanciesJob / `job-vacancy:sync`).
    |
    */

    'http' => [
        'timeout'        => (int) env('NAWASARA_JOB_VACANCY_HTTP_TIMEOUT', 15),
        'retry_times'    => (int) env('NAWASARA_JOB_VACANCY_HTTP_RETRY', 1),
        'retry_sleep_ms' => (int) env('NAWASARA_JOB_VACANCY_HTTP_RETRY_SLEEP', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job vacancies (pull from upstream)
    |--------------------------------------------------------------------------
    |
    | per_page     : items requested per page from upstream (upstream max = 100).
    | max_pages    : maximum pages pulled per sync cycle.
    |                Total snapshot = per_page × max_pages.
    | service      : service name in the server contract `/api/v1/{service}/{resource}`
    |                (default `loker` — the legacy Indonesian upstream endpoint).
    | version      : API version prefix before the service name (default `v1`).
    | resource     : resource beneath the service (default `lokers`) → `/api/v1/loker/lokers`.
    | default_per_page: default per_page on the Nawasara API.
    | max_per_page : per_page cap on the Nawasara API.
    | api.rate_limit_per_minute: rate limit for `GET /api/v1/job-vacancy/job-vacancies`
    |                per (token + IP); exceeding → `429`.
    |
    */

    'job_vacancy' => [
        'service'          => (string) env('NAWASARA_JOB_VACANCY_SERVICE', 'loker'),
        'version'          => (string) env('NAWASARA_JOB_VACANCY_VERSION', 'v1'),
        'resource'         => (string) env('NAWASARA_JOB_VACANCY_RESOURCE', 'lokers'),
        'per_page'         => (int) env('NAWASARA_JOB_VACANCY_PER_PAGE', 100),
        'max_pages'        => (int) env('NAWASARA_JOB_VACANCY_MAX_PAGES', 3),
        'default_per_page' => (int) env('NAWASARA_JOB_VACANCY_DEFAULT_PER_PAGE', 50),
        'max_per_page'     => (int) env('NAWASARA_JOB_VACANCY_MAX_PER_PAGE', 100),
        'api' => [
            'rate_limit_per_minute' => (int) env('NAWASARA_JOB_VACANCY_RATE_LIMIT_PER_MINUTE', 120),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled sync
    |--------------------------------------------------------------------------
    |
    | every_minutes : `job-vacancy:sync` interval in minutes. 0 = disabled.
    |                 The local DB snapshot is filled by this command; API & UI
    |                 read from the DB so they never touch upstream per request.
    |
    */

    'sync' => [
        'every_minutes' => (int) env('NAWASARA_JOB_VACANCY_SYNC_EVERY_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vault
    |--------------------------------------------------------------------------
    |
    | Credential group name in Vault (nawasara/vault).
    | This group stores: base_url, api_token.
    |
    */

    'vault' => [
        'group' => env('NAWASARA_JOB_VACANCY_VAULT_GROUP', 'job-vacancy'),
    ],
];