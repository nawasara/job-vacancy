<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Nawasara\JobVacancy\Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MigrationAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_fresh_table_with_english_columns(): void
    {
        Schema::dropIfExists('nawasara_job_vacancies');

        $migration = require base_path('packages/nawasara-job-vacancy/database/migrations/2026_09_19_000001_create_nawasara_job_vacancies_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('nawasara_job_vacancies'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'source_id'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'slug'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'job_title'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'job_description'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'company_name'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'company_description'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'company_url'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'company_address'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'location'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'salary'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'type'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'category'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'apply_url'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'requirements'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'expires_at'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'is_expired'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'is_active'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'extern_created_at'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'extern_updated_at'));
        $this->assertTrue(Schema::hasColumn('nawasara_job_vacancies', 'synced_at'));
        $this->assertFalse(Schema::hasColumn('nawasara_job_vacancies', 'nama_pekerjaan'));
    }

    public function test_migration_is_idempotent(): void
    {
        Schema::dropIfExists('nawasara_job_vacancies');

        $migration = require base_path('packages/nawasara-job-vacancy/database/migrations/2026_09_19_000001_create_nawasara_job_vacancies_table.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasTable('nawasara_job_vacancies'));
    }

    public function test_permission_seeder_creates_permission_and_role(): void
    {
        (new PermissionSeeder())->run();

        $this->assertTrue(Permission::where('name', 'job.vacancy.view')->where('guard_name', 'web')->exists());

        $role = Role::where('name', 'job-vacancy')->where('guard_name', 'web')->first();

        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('job.vacancy.view'));
    }
}