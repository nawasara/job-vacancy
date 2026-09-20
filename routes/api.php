<?php

use Illuminate\Support\Facades\Route;
use Nawasara\JobVacancy\Http\Api\JobVacancyController;

/*
|--------------------------------------------------------------------------
| Endpoint WARGA, di belakang JWT Keycloak realm warga
|--------------------------------------------------------------------------
| Dimuat oleh JobVacancyServiceProvider dengan middleware `api.citizen`,
| jalur yang sama dengan PBB dan laporan warga.
|
| TANPA `scope:` di sini. RequireScope membaca `api_token` dari atribut
| permintaan, dan atribut itu hanya diisi jalur token `nws_`. Dipasang pada
| rute warga, ia menolak SEMUA orang dengan 401 "Token tidak terauthentikasi
| sebelum cek scope" meski JWT-nya sah. Scope `job.vacancy.read` tetap
| terdaftar di ScopeRegistry untuk kelak dipakai integrasi sistem lain.
|
| Dua endpoint ini hanya membaca, dan yang dibaca tidak bergantung pada siapa
| yang bertanya: seluruh warga melihat daftar yang sama. Identitasnya dipakai
| untuk batas laju per orang, bukan untuk menyaring data.
*/

Route::get('/job-vacancies', [JobVacancyController::class, 'index'])->name('job-vacancies.index');
Route::get('/job-vacancies/{slug}', [JobVacancyController::class, 'show'])->name('job-vacancies.show');