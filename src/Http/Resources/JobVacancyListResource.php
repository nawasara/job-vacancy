<?php

namespace Nawasara\JobVacancy\Http\Resources;

use Illuminate\Http\Request;

/**
 * Compact variant for the LIST endpoint — optimized for mobile apps
 * (Android/iOS): only the essential meta fields per item, without the heavy
 * description/requirements. Full detail is fetched per item via
 * GET /job-vacancy/job-vacancies/{slug} (JobVacancyResource).
 *
 * Internal columns are equally blocked (inherited from JobVacancyResource):
 * `source_id`, `is_active`, `synced_at`, `extern_*`.
 */
class JobVacancyListResource extends JobVacancyResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug'       => $this->slug,
            'job_title'  => $this->job_title,
            'company'    => $this->company_name,
            'location'   => $this->location,
            'type'       => $this->type,
            'category'   => $this->category,
            'salary'     => $this->salary,
            'is_expired' => (bool) $this->is_expired,
            'expires_at' => $this->iso8601($this->expires_at),
        ];
    }
}