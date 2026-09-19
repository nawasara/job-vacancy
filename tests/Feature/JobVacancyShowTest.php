<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Nawasara\JobVacancy\Livewire\JobVacancy\Show;
use Nawasara\JobVacancy\Models\JobVacancy;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class JobVacancyShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'job.vacancy.view', 'guard_name' => 'web']);
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('job.vacancy.view');
        $this->actingAs($user);

        return $user;
    }

    private function createJobVacancy(array $attributes = []): JobVacancy
    {
        return JobVacancy::create(array_merge([
            'source_id'         => (string) Str::uuid(),
            'slug'              => 'job-'.Str::slug($attributes['slug'] ?? Str::uuid()->toString()),
            'job_title'         => 'Computer Operator',
            'job_description'   => "Short description.\nSecond line.",
            'company_name'      => 'Example Corp',
            'location'          => 'Jakarta',
            'type'              => 'Full-time',
            'category'          => 'IT',
            'salary'            => 'Rp 3.000.000',
            'is_expired'        => false,
            'is_active'         => true,
            'extern_created_at' => now(),
        ], $attributes));
    }

    public function test_show_renders_description_and_editorjs_without_prose(): void
    {
        $this->actingUser();

        $this->createJobVacancy([
            'slug'         => 'finance-admin',
            'job_title'    => 'Finance Admin',
            'requirements' => [
                'time' => 1,
                'blocks' => [
                    ['type' => 'header', 'data' => ['level' => 3, 'text' => 'Qualifications']],
                    ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['Bachelor degree']]],
                ],
            ],
        ]);

        Livewire::test(Show::class, ['slug' => 'finance-admin'])
            ->assertSee('Finance Admin')
            ->assertSee('Short description.')
            ->assertSee('Second line')
            ->assertSee('Qualifications')
            ->assertSee('<ul', false)
            ->assertDontSee('prose');
    }

    public function test_show_aborts_for_unknown_or_expired_vacancy(): void
    {
        $this->actingUser();

        $this->createJobVacancy(['slug' => 'stale', 'is_expired' => true]);

        Livewire::test(Show::class, ['slug' => 'stale'])
            ->assertStatus(404);
    }
}