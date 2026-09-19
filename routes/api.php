<?php

use Illuminate\Support\Facades\Route;
use Nawasara\JobVacancy\Http\Api\JobVacancyController;

Route::middleware('scope:job.vacancy.read')->group(function () {
    Route::get('/job-vacancies', [JobVacancyController::class, 'index'])->name('job-vacancies.index');
    Route::get('/job-vacancies/{slug}', [JobVacancyController::class, 'show'])->name('job-vacancies.show');
});