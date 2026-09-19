<?php

namespace Nawasara\JobVacancy;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Nawasara\JobVacancy\Console\Commands\InstallCommand;
use Nawasara\JobVacancy\Console\Commands\SyncJobVacanciesCommand;
use Nawasara\JobVacancy\Jobs\SyncJobVacanciesJob;

class JobVacancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-job-vacancy.php', 'nawasara-job-vacancy');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-job-vacancy');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/nawasara-job-vacancy.php' => config_path('nawasara-job-vacancy.php'),
        ], 'nawasara-job-vacancy-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                SyncJobVacanciesCommand::class,
            ]);
        }

        $this->registerLivewire();
        $this->registerApiScopes();
        $this->registerApiRateLimiter();
        $this->registerApiRoutes();

        $this->app->booted(function () {
            if (! $this->app->runningInConsole()) {
                return;
            }

            $minutes = (int) config('nawasara-job-vacancy.sync.every_minutes', 15);

            if ($minutes > 0) {
                $this->app->make(Schedule::class)
                    ->call(fn () => SyncJobVacanciesJob::dispatch(triggerSource: 'scheduled'))
                    ->name('job-vacancy:sync')
                    ->cron("*/{$minutes} * * * *")
                    ->withoutOverlapping(5);
            }
        });
    }

    /**
     * Auto-register Livewire components. Alias format: nawasara-job-vacancy.<path-kebab>.
     */
    protected function registerLivewire(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            return;
        }

        $aliases = [
            \Nawasara\JobVacancy\Livewire\JobVacancy\Index::class => 'nawasara-job-vacancy.job-vacancy.index',
            \Nawasara\JobVacancy\Livewire\JobVacancy\Show::class  => 'nawasara-job-vacancy.job-vacancy.show',
        ];

        foreach ($aliases as $component => $alias) {
            \Livewire\Livewire::component($alias, $component);
        }
    }

    /**
     * Register API scopes BEFORE routes (scope mismatch detection).
     */
    protected function registerApiScopes(): void
    {
        if (! class_exists(\Nawasara\Api\Support\ScopeRegistry::class)) {
            return;
        }

        $this->app->make(\Nawasara\Api\Support\ScopeRegistry::class)->register(
            'job.vacancy.read',
            'List + detail of active job vacancies from the upstream snapshot. Does not include submissions/forms.',
        );
    }

    /**
     * Mount API routes under /api/v1/job-vacancy/job-vacancies. Guarded by class_exists.
     */
    protected function registerApiRoutes(): void
    {
        if (! class_exists(\Nawasara\Api\ApiServiceProvider::class)) {
            return;
        }

        $prefix = (string) config('nawasara-api.route.prefix', 'api/v1').'/job-vacancy';

        \Illuminate\Support\Facades\Route::prefix($prefix)
            ->middleware(['api', 'api.auth', 'api.log', 'throttle:job-vacancy-api'])
            ->name('nawasara-api.job-vacancy.')
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Rate limiter for the public endpoint — keyed per (token + IP), not per IP
     * alone, so legitimate mobile clients behind a shared public IP do not eat
     * each other's quota (pattern `nawasara-citizen` in nawasara/api).
     * Amount is controlled by `nawasara-job-vacancy.job_vacancy.api.rate_limit_per_minute`.
     * MUST be registered BEFORE routes use `throttle:job-vacancy-api`.
     */
    protected function registerApiRateLimiter(): void
    {
        RateLimiter::for('job-vacancy-api', function ($request) {
            $perMinute = (int) config('nawasara-job-vacancy.job_vacancy.api.rate_limit_per_minute', 120);

            $tokenId = $request->attributes->get('api_token')?->id ?? 'anon';

            return Limit::perMinute($perMinute)->by('job-vacancy:'.$tokenId.':'.$request->ip());
        });
    }
}