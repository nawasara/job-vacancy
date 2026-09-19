# Nawasara Job Vacancy

**Job vacancies** module for the Nawasara superapp framework — a read-only mirror of job vacancy
data from the upstream service management platform into a local DB snapshot.

This is the **English port of `nawasara/loker`**: the local table, API, UI and error strings are all
English (`job vacancies`, `job_title`, `company_name`, `job_description`, ...) while the upstream
contract stays the default `loker` / `lokers` endpoints for compatibility with the external service.

Uses UI components from **nawasara-ui**, connection credentials handled by **nawasara-vault**, and
data served via **nawasara-api**. The architecture pattern follows `nawasara-zoom` / `nawasara-cloudflare`.

## Features

- Periodic sync (default every 15 minutes) snapshots job vacancies from the upstream platform into the local DB
- List UI (search + filter) with detail opened in a **modal** (`x-nawasara-ui::modal`)
- Standalone detail page (`/nawasara-job-vacancy/job-vacancies/{slug}`)
- Renders `requirements` (EditorJS) via the **`x-nawasara-job-vacancy::editorjs-renderer`** component — XSS-safe (all text `e()`)
- Nawasara read-only API with scope `job.vacancy.read`
- Permission `job.vacancy.view` + role `job-vacancy`
- Idempotent migration creating the fresh English table `nawasara_job_vacancies`

## Setup

Install:

```bash
php artisan job-vacancy:install
```

This command (idempotent):

1. (Optional) publishes `config/nawasara-job-vacancy.php` — default no; without publishing, the config is still read via `mergeConfigFrom` and can be overridden through `.env`
2. Runs the migration
3. Seeds role `job-vacancy` + permission `job.vacancy.view`
4. Injects the Vault group `job-vacancy` into `config/nawasara-vault.php` (when the Vault config is already published)

Add it manually when the Vault config is not published:

```php
'groups' => [
    'job-vacancy' => [
        'label'  => 'Job Vacancies',
        'icon'   => 'lucide-briefcase',
        'test'   => \Nawasara\JobVacancy\Services\JobVacancyClient::class.'@testConnection',
        'fields' => [
            'base_url'  => ['label' => 'Base URL', 'type' => 'text', 'placeholder' => 'https://jobs.example.com'],
            'api_token' => ['label' => 'API Token', 'type' => 'password'],
        ],
    ],
],
```

## Configuration

`NAWASARA_JOB_VACANCY_VAULT_GROUP` — Vault group name (default `job-vacancy`).
`NAWASARA_JOB_VACANCY_SYNC_EVERY_MINUTES` — sync interval in minutes (default `15`; `0` = disabled).

The `http`, `job_vacancy`, `sync` and `vault` config keys are documented in `config/nawasara-job-vacancy.php`.

## Usage

### Vault

Fill in the credentials in the **Vault → group `job-vacancy`** menu:

| Field     | Value                                                                                                                                 |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| Base URL  | Upstream host root, e.g. `https://jobs.example.com` (the path `/api/v1/loker/lokers` is appended automatically by `JobVacancyClient`) |
| API Token | Upstream `loker` contract token                                                                                                       |

Connection check is available via the **Test** button in Vault (`JobVacancyClient@testConnection`).

### Sync

Sync uses the **queue job** `SyncJobVacanciesJob` (extends `Nawasara\Sync\Jobs\AbstractSyncJob` —
tracked in `nawasara_sync_jobs`, retry 3× with backoff, queue routing falls back to the `default`
queue when no dedicated queue key is configured).

**Dispatch (default):**

```bash
php artisan job-vacancy:sync
```

**Inline execution (first run / debug, skip the queue):**

```bash
php artisan job-vacancy:sync --sync
```

**Scheduled:** the dispatcher is registered `*/15 * * * *` (interval via
`NAWASARA_JOB_VACANCY_SYNC_EVERY_MINUTES`; `0` = disabled) using
`$schedule->call(fn () => SyncJobVacanciesJob::dispatch(triggerSource: 'scheduled'))` — following the
`reference_schedule_call_workaround` pattern (not `$schedule->command(...)`).

Data is served from the local DB snapshot, **not** from the upstream per request — the API and UI
keep working when the upstream is unreachable (as long as a snapshot exists).

### UI

- `/nawasara-job-vacancy/job-vacancies` — list + search + filter, detail via modal (route `nawasara-job-vacancy.job-vacancy.index`)
- `/nawasara-job-vacancy/job-vacancies/{slug}` — detail page (route `nawasara-job-vacancy.job-vacancy.show`)

Access is restricted by permission `job.vacancy.view` (role `job-vacancy`).

### Permissions

```
job.vacancy.view
```

## Nawasara API (for other applications)

Requires [`nawasara/api`](../../packages/nawasara-api). When not installed, routes are not mounted.

Served from the local snapshot, not from the upstream directly.

### Scope

| Scope              | Access                                                                                                                     |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------- |
| `job.vacancy.read` | List + detail of job vacancies (slug, title, description, company, location, salary, type, category, requirements, expiry) |

### Endpoint

| Method | Path                                       | Query                                           |
| ------ | ------------------------------------------ | ----------------------------------------------- |
| GET    | `/api/v1/job-vacancy/job-vacancies`        | `q`, `location`, `type`, `category`, `per_page` |
| GET    | `/api/v1/job-vacancy/job-vacancies/{slug}` |                                                 |

The list uses a **compact payload** (`JobVacancyListResource`) optimized for Android/iOS apps —
each item only carries meta fields (slug, job title, company, location, type, category, salary,
expired flag, expiry date). Description, requirements and the apply link are fetched per item through
the detail endpoint.

The `q` search covers job title, company name, **and job description**. Only **active and
not-yet-expired** vacancies are listed; expired ones do not appear in the list and resolve to `404`
on the detail endpoint. Pagination is stabilized with an `id` tie-breaker so ordering stays stable
while data changes.

**Security & rate limiting:**

- `job_description` is HTML that is **already sanitized** (tag-whitelist; `<script>`/`<style>`/
  event handlers/`javascript:`/`data:` stripped; formatting `<b>/<a>/<em>` preserved). Clients may
  render this field as safe rich text — do not double-render values from any other source.
- Endpoints are **rate limited** to a default of `120` requests/minute per **token + IP**
  (`config('nawasara-job-vacancy.job_vacancy.api.rate_limit_per_minute')`, env
  `NAWASARA_JOB_VACANCY_RATE_LIMIT_PER_MINUTE`); exceeding → `429`.

```bash
curl -H "Authorization: Bearer nws_xxx" \
  "https://job-vacancy.example.org/api/v1/job-vacancy/job-vacancies?q=operator&per_page=10"
```

### Never returned

- **`id`** (internal upstream UUID), **`is_active`**, **`is_expired`**, **`deleted_at`**, and the
  internal sync fields (`source_id`, `extern_*`, `synced_at`) stay private.

## Database

| Table                    | Purpose                                                                       |
| ------------------------ | ----------------------------------------------------------------------------- |
| `nawasara_job_vacancies` | Job vacancy snapshot (filled by the periodic sync from the upstream platform) |

Model: `Nawasara\JobVacancy\Models\JobVacancy`. The public id is the `slug`, not the internal
upstream UUID.

## Cross-package Integration

| Package            | Role                                                                        |
| ------------------ | --------------------------------------------------------------------------- |
| **nawasara/vault** | Stores the sync credentials (group `job-vacancy`: `base_url` + `api_token`) |
| **nawasara/api**   | Mounts `routes/api.php` (scope `job.vacancy.read`)                          |
| **nawasara/ui**    | Page/table/modal/filter components and the editorjs-renderer                |

## Troubleshooting

### Vault group missing or empty

Make sure Vault has a `job-vacancy` group with `base_url` + `api_token` filled in:

```bash
php artisan vault:show
```

### Empty UI/API data

Run the sync (inline to see the result immediately):

```bash
php artisan job-vacancy:sync --sync
```

### New vacancies appear late

Bounded staleness until the next scheduled sync — lower
`NAWASARA_JOB_VACANCY_SYNC_EVERY_MINUTES` if needed.

## Author

[Ricky R](mailto:ricky.romdhoni@gmail.com) — mini dev

## License

MIT
