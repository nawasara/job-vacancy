<?php

namespace Nawasara\JobVacancy\Livewire\JobVacancy;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\JobVacancy\Models\JobVacancy;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $location = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $category = '';

    public int $perPage = 8;

    public array $detail = [];

    public string $detailHtml = '';

    protected function rules(): array
    {
        return [
            'q'        => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'type'     => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'perPage'  => ['required', 'integer', 'in:4,8,12,24'],
        ];
    }

    #[Computed]
    public function jobVacancies()
    {
        Gate::authorize('job.vacancy.view');

        return JobVacancy::query()
            ->active()
            ->when($this->q !== '', fn ($query) => $this->applySearchFilter($query))
            ->when($this->location !== '', fn ($query) => $query->where('location', 'like', "%{$this->location}%"))
            ->when($this->type !== '', fn ($query) => $query->where('type', 'like', "%{$this->type}%"))
            ->when($this->category !== '', fn ($query) => $query->where('category', 'like', "%{$this->category}%"))
            ->orderByDesc('extern_created_at')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function locationOptions(): array
    {
        return $this->distinctItems('location');
    }

    #[Computed]
    public function typeOptions(): array
    {
        return $this->distinctItems('type');
    }

    #[Computed]
    public function categoryOptions(): array
    {
        return $this->distinctItems('category');
    }

    private function distinctItems(string $column): array
    {
        return JobVacancy::query()
            ->active()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->mapWithKeys(fn (string $value) => [$value => $value])
            ->all();
    }

    public function updatedQ(): void
    {
        $this->q = trim($this->q);
        $this->resetPage();
    }

    public function updatedLocation(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'location', 'type', 'category']);
        $this->resetPage();
    }

    /**
     * Search filters over job_title / company_name using the sanitized term.
     * The pattern is bound (no SQL injection) and the LIKE wildcards (`%`,
     * `_`) of the user input are escaped with an explicit `ESCAPE` clause so
     * they are matched literally on every driver (MySQL, SQLite, ...).
     */
    protected function applySearchFilter($query): void
    {
        $term = $this->sanitizeSearchTerm($this->q);

        if ($term === '') {
            return;
        }

        $pattern = "%{$term}%";

        $query->where(function ($builder) use ($pattern): void {
            $builder->whereRaw('job_title LIKE ? ESCAPE \'\\\'', [$pattern])
                ->orWhereRaw('company_name LIKE ? ESCAPE \'\\\'', [$pattern]);
        });
    }

    /**
     * Sanitize a search term: trim surrounding whitespace and escape the LIKE
     * wildcards `%`, `_` and `\` so they are treated as literal characters.
     */
    protected function sanitizeSearchTerm(string $value): string
    {
        $value = trim($value);

        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /**
     * Load the detail of one job vacancy for the modal (server-rendered,
     * including editorjs). Unknown slug / already expired → inline message,
     * modal stays open.
     */
    public function openDetail(string $slug): void
    {
        Gate::authorize('job.vacancy.view');

        $jobVacancy = JobVacancy::query()
            ->active()
            ->where('slug', $slug)
            ->first();

        if ($jobVacancy === null) {
            $this->detail = ['job_title' => 'Job vacancy not found', 'slug' => ''];
            $this->detailHtml = '<p class="text-sm text-gray-500 dark:text-neutral-500">'.
                'Job vacancy not found or already expired.</p>';
        } else {
            $this->detail = [
                'job_title'  => $jobVacancy->job_title,
                'company'    => $jobVacancy->company_name,
                'location'   => $jobVacancy->location,
                'slug'       => $jobVacancy->slug,
            ];
            $this->detailHtml = view('nawasara-job-vacancy::livewire.modal.job-vacancy-detail', ['jobVacancy' => $jobVacancy])->render();
        }

        $this->dispatch('modal-open:job-vacancy-detail');
    }

    public function render()
    {
        return view('nawasara-job-vacancy::livewire.pages.job-vacancy.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}