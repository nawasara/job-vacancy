<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Nawasara\JobVacancy\Livewire\JobVacancy\Index;
use Nawasara\JobVacancy\Models\JobVacancy;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class JobVacancyIndexTest extends TestCase
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
            'job_description'   => 'Short description.',
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

    public function test_index_renders_vacancies_in_a_table(): void
    {
        $this->actingUser();

        $this->createJobVacancy([
            'slug'         => 'computer-operator',
            'job_title'    => 'Computer Operator',
            'company_name' => 'Example Corp',
        ]);

        Livewire::test(Index::class)
            ->assertSee('Computer Operator')
            ->assertSee('Example Corp')
            ->assertSee('Location');
    }

    public function test_detail_renders_compact_preview_in_modal(): void
    {
        $this->actingUser();

        $this->createJobVacancy([
            'slug'       => 'finance-admin',
            'job_title'  => 'Finance Admin',
            'company_name' => 'Example Corp',
            'requirements' => [
                'time' => 1,
                'blocks' => [
                    ['type' => 'header', 'data' => ['level' => 3, 'text' => 'Qualifications']],
                    ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['Bachelor degree']]],
                ],
            ],
        ]);

        Livewire::test(Index::class)
            ->call('openDetail', 'finance-admin')
            ->assertSet('detail.job_title', 'Finance Admin')
            ->assertSet('detail.slug', 'finance-admin')
            ->assertDispatched('modal-open:job-vacancy-detail')
            ->assertSet('detailHtml', fn (string $html) => str_contains($html, 'Location')
                && str_contains($html, 'Salary')
                && ! str_contains($html, 'Qualifications')
                && ! str_contains($html, '<ul'));
    }

    public function test_detail_missing_slug_is_graceful(): void
    {
        $this->actingUser();

        Livewire::test(Index::class)
            ->call('openDetail', 'does-not-exist')
            ->assertSet('detail.job_title', 'Job vacancy not found')
            ->assertDispatched('modal-open:job-vacancy-detail')
            ->assertSet('detailHtml', fn (string $html) => str_contains($html, 'already expired'));
    }

    public function test_search_filters_by_title_or_company(): void
    {
        $this->actingUser();

        $this->createJobVacancy([
            'slug'         => 'computer-operator',
            'job_title'    => 'Computer Operator',
            'company_name' => 'Example Corp',
        ]);
        $this->createJobVacancy([
            'slug'         => 'graphic-designer',
            'job_title'    => 'Graphic Designer',
            'company_name' => 'Design Studio',
        ]);

        Livewire::test(Index::class)
            ->set('q', 'operator')
            ->assertSee('Computer Operator')
            ->assertDontSee('Graphic Designer');

        Livewire::test(Index::class)
            ->set('q', 'studio')
            ->assertSee('Graphic Designer')
            ->assertDontSee('Computer Operator');
    }

    public function test_search_sanitizes_like_wildcards(): void
    {
        $this->actingUser();

        $this->createJobVacancy([
            'slug'      => 'discount-host',
            'job_title' => 'Host 50% Off',
        ]);
        $this->createJobVacancy([
            'slug'      => 'database-admin',
            'job_title' => 'Database Admin',
        ]);

        Livewire::test(Index::class)
            ->set('q', '%')
            ->assertSee('Host 50% Off')
            ->assertDontSee('Database Admin');
    }

    public function test_search_trims_whitespace(): void
    {
        $this->actingUser();

        $this->createJobVacancy([
            'slug'      => 'computer-operator',
            'job_title' => 'Computer Operator',
        ]);

        Livewire::test(Index::class)
            ->set('q', '  operator  ')
            ->assertSet('q', 'operator')
            ->assertSee('Computer Operator');
    }

    public function test_search_input_uses_debounced_wire_model(): void
    {
        $this->actingUser();

        Livewire::test(Index::class)
            ->assertSeeHtml('wire:model.live.debounce.300ms="q"');
    }
}