<?php

namespace Nawasara\JobVacancy\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\JobVacancy\Http\Resources\JobVacancyListResource;
use Nawasara\JobVacancy\Http\Resources\JobVacancyResource;
use Nawasara\JobVacancy\Models\JobVacancy;

/**
 * Public endpoint — reads the `nawasara_job_vacancies` DB snapshot.
 * No HTTP calls to the upstream on the request path.
 */
class JobVacancyController extends Controller
{
    /**
     * GET /api/v1/job-vacancy/job-vacancies
     *
     * Reads from the DB snapshot, optional filters, Eloquent pagination.
     * Standard envelope: {data: [...], meta: {total, per_page, current_page, last_page}}.
     */
    public function index(Request $request): JsonResponse
    {
        $maxPerPage = (int) config('nawasara-job-vacancy.job_vacancy.max_per_page', 100);
        $perPage    = min($maxPerPage, max(1, (int) $request->query('per_page', (int) config('nawasara-job-vacancy.job_vacancy.default_per_page', 50))));

        $query = JobVacancy::query()
            ->active()
            // `id` tie-breaker keeps pagination stable while data changes (add-api guide).
            ->orderByDesc('extern_created_at')
            ->orderByDesc('id');

        $q         = trim((string) $request->query('q', ''));
        $location  = trim((string) $request->query('location', ''));
        $type      = trim((string) $request->query('type', ''));
        $category  = trim((string) $request->query('category', ''));

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('job_title', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%")
                    ->orWhere('job_description', 'like', "%{$q}%");
            });
        }

        if ($location !== '') {
            $query->where('location', 'like', "%{$location}%");
        }

        if ($type !== '') {
            $query->where('type', 'like', "%{$type}%");
        }

        if ($category !== '') {
            $query->where('category', 'like', "%{$category}%");
        }

        $paginated = $query->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => JobVacancyListResource::collection($paginated->items())->resolve(),
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/job-vacancy/job-vacancies/{slug}
     *
     * Resolves from the DB snapshot by slug. Missing → 404. Success → {data: {...}}.
     */
    public function show(string $slug): JsonResponse
    {
        $jobVacancy = JobVacancy::query()
            ->active()
            ->where('slug', $slug)
            ->first();

        if ($jobVacancy === null) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'Job vacancy not found.',
            ], 404);
        }

        return response()->json([
            'data' => (new JobVacancyResource($jobVacancy))->resolve(request()),
        ]);
    }
}