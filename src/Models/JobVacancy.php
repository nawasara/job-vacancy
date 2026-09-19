<?php

namespace Nawasara\JobVacancy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Local snapshot of job vacancy data from the upstream API.
 *
 * Columns `source_id`, `is_active`, `synced_at`, `extern_*` are internal —
 * NOT exposed through JobVacancyResource.
 */
class JobVacancy extends Model
{
    protected $table = 'nawasara_job_vacancies';

    protected $guarded = [];

    protected $casts = [
        'requirements'        => 'array',
        'is_expired'          => 'boolean',
        'is_active'           => 'boolean',
        'expires_at'          => 'datetime',
        'extern_created_at'   => 'datetime',
        'extern_updated_at'   => 'datetime',
        'synced_at'           => 'datetime',
    ];

    /**
     * Job vacancies that are still live: not expired and still active. Required
     * condition on every list/detail (API, Livewire, filter dropdown) — defined once here.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_expired', false)->where('is_active', true);
    }
}