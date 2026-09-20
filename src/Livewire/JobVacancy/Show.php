<?php

namespace Nawasara\JobVacancy\Livewire\JobVacancy;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\JobVacancy\Models\JobVacancy;

class Show extends Component
{
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    #[Computed]
    public function jobVacancy()
    {
        Gate::authorize('job.vacancy.view');

        $jobVacancy = JobVacancy::query()
            ->active()
            ->where('slug', $this->slug)
            ->first();

        if ($jobVacancy === null) {
            abort(404, 'Lowongan tidak ditemukan.');
        }

        return $jobVacancy;
    }

    public function render()
    {
        return view('nawasara-job-vacancy::livewire.pages.job-vacancy.show')
            ->layout('nawasara-ui::components.layouts.app');
    }
}