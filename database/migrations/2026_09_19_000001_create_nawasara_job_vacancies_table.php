<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fresh snapshot table for job vacancies (English port of loker).
     * No legacy rename — this package has no historical table.
     * Idempotent & safe to run repeatedly.
     */
    public function up(): void
    {
        if (Schema::hasTable('nawasara_job_vacancies')) {
            return;
        }

        Schema::create('nawasara_job_vacancies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('source_id')->unique();
            $table->string('slug')->unique();

            $table->string('job_title');
            $table->text('job_description')->nullable();
            $table->string('company_name');
            $table->text('company_description')->nullable();
            $table->string('company_url')->nullable();
            $table->string('company_address')->nullable();
            $table->string('location')->nullable()->index();
            $table->string('salary')->nullable();
            $table->string('type')->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->text('apply_url')->nullable();
            $table->json('requirements')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_expired')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('extern_created_at')->nullable();
            $table->timestamp('extern_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('nawasara_job_vacancies')) {
            Schema::drop('nawasara_job_vacancies');
        }
    }
};