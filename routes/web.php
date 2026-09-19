<?php

use Illuminate\Support\Facades\Route;
use Nawasara\JobVacancy\Livewire\JobVacancy\Index as JobVacancyIndex;
use Nawasara\JobVacancy\Livewire\JobVacancy\Show as JobVacancyShow;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth', PermissionMiddleware::using('job.vacancy.view')])
    ->prefix('nawasara-job-vacancy')
    ->group(function () {
        Route::get('job-vacancies', JobVacancyIndex::class)->name('nawasara-job-vacancy.job-vacancy.index');

        Route::get('job-vacancies/{slug}', JobVacancyShow::class)->name('nawasara-job-vacancy.job-vacancy.show');
    });