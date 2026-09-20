<?php

namespace Nawasara\JobVacancy\Livewire\JobVacancy;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\JobVacancy\Jobs\SyncJobVacanciesJob;
use Nawasara\JobVacancy\Models\JobVacancy;
use Nawasara\Sync\Concerns\TracksLastSync;

class Index extends Component
{
    use TracksLastSync;
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
     * Kapan Rakaca terakhir berhasil ditarik.
     *
     * Dibaca dari riwayat nawasara/sync, bukan dari kolom di tabel lowongan:
     * sinkronisasi yang berjalan tetapi tidak mengubah satu baris pun TETAP
     * sinkronisasi yang berhasil, dan stempel di baris data tidak dapat
     * membedakannya dari "sudah lama tidak jalan".
     *
     * null berarti belum pernah berhasil sekali pun, dan sync-info-bar
     * menggambarnya sebagai peringatan, bukan sebagai "baru saja".
     */
    #[Computed]
    public function lastSyncedAt(): ?string
    {
        $when = $this->lastSuccessfulSyncAt('job-vacancy', 'sync_job_vacancies');

        return $when?->diffForHumans();
    }

    /**
     * Tarik ulang dari Rakaca sekarang, tanpa menunggu jadwal 15 menit.
     *
     * Dijalankan ANTREAN, bukan langsung: satu siklus menarik beberapa halaman
     * dari Rakaca dan dapat memakan waktu lebih lama daripada yang pantas
     * ditunggu di depan layar. Staf mendapat pemberitahuan segera, dan
     * hasilnya muncul begitu antrean selesai.
     */
    public function syncNow(): void
    {
        Gate::authorize('job.vacancy.view');

        SyncJobVacanciesJob::dispatch(triggerSource: 'manual');

        unset($this->lastSyncedAt);

        $this->dispatch('toast', type: 'success', message: 'Sinkronisasi dijalankan. Daftar diperbarui begitu selesai.');
    }

    /**
     * Search filters over job_title / company_name using the sanitized term.
     * The pattern is bound (no SQL injection) and the LIKE wildcards (`%`,
     * `_`) of the user input are escaped with an explicit `ESCAPE '!'` clause
     * so they are matched literally on every driver (MySQL, SQLite, ...).
     * `!` is used as the escape character because MySQL treats a literal
     * backslash inside a string as an escape sequence (`ESCAPE '\'` is a
     * syntax error there), while `ESCAPE '!'` is portable across drivers.
     */
    protected function applySearchFilter($query): void
    {
        $term = $this->sanitizeSearchTerm($this->q);

        if ($term === '') {
            return;
        }

        $pattern = "%{$term}%";

        $query->where(function ($builder) use ($pattern): void {
            $builder->whereRaw('job_title LIKE ? ESCAPE \'!\'', [$pattern])
                ->orWhereRaw('company_name LIKE ? ESCAPE \'!\'', [$pattern]);
        });
    }

    /**
     * Sanitize a search term: trim surrounding whitespace and escape the LIKE
     * escape character `!` and the wildcards `%`/`_` so they are treated as
     * literal characters.
     */
    protected function sanitizeSearchTerm(string $value): string
    {
        $value = trim($value);

        // Escape `!` first (it becomes `!!`), then the wildcards: a literal
        // `%` → `!%` and a literal `_` → `!_` when parsed with `ESCAPE '!'`.
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
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
            $this->detail = ['job_title' => 'Lowongan tidak ditemukan', 'slug' => ''];
            $this->detailHtml = '<p class="text-sm text-gray-500 dark:text-neutral-500">'.
                'Lowongan tidak ditemukan atau sudah berakhir.</p>';
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