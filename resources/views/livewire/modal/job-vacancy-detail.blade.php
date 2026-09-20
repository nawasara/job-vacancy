{{--
    Modal content partial — COMPACT PREVIEW before entering the detail page.
    Received as `$jobVacancy` (server-side render from Index::openDetail() → $detailHtml).

    Short content: concise meta (location/type/category/salary/expires/status) +
    description excerpt (line-clamp) + Apply button. Full detail (full description
    + editorjs requirements) opens on the Show page.
--}}
<div class="space-y-5">
    {{-- Concise meta --}}
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div class="flex flex-col gap-0.5">
            <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-neutral-500">Lokasi</dt>
            <dd class="text-gray-900 dark:text-neutral-100">{{ $jobVacancy->location ?: '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-neutral-500">Gaji</dt>
            <dd class="font-medium text-gray-900 dark:text-neutral-100">{{ $jobVacancy->salary ?: '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-neutral-500">Tipe</dt>
            <dd class="text-gray-900 dark:text-neutral-100">{{ $jobVacancy->type ?: '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-neutral-500">Kategori</dt>
            <dd class="text-gray-900 dark:text-neutral-100">{{ $jobVacancy->category ?: '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-neutral-500">Berakhir</dt>
            <dd class="text-gray-900 dark:text-neutral-100">{{ $jobVacancy->expires_at?->format('d M Y') ?: '—' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-2">
            <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-neutral-500">Status</dt>
            <dd>
                @if ($jobVacancy->is_expired)
                    <x-nawasara-ui::badge color="neutral" size="sm">Kedaluwarsa</x-nawasara-ui::badge>
                @else
                    <x-nawasara-ui::badge color="success" size="sm" icon="lucide-circle-check">Aktif</x-nawasara-ui::badge>
                @endif
            </dd>
        </div>
    </dl>

    {{-- Description excerpt --}}
    @if ($jobVacancy->job_description)
        <p class="line-clamp-3 text-sm leading-relaxed text-gray-600 dark:text-neutral-400">
            {{ \Illuminate\Support\Str::limit($jobVacancy->job_description, 260) }}
        </p>
    @endif

    {{-- Actions --}}
    @php
        $applyUrl = $jobVacancy->apply_url ?? '';
    @endphp
    @if (filter_var($applyUrl, FILTER_VALIDATE_URL))
        <x-nawasara-ui::button color="primary" size="sm" :href="$applyUrl" target="_blank">
            Apply
        </x-nawasara-ui::button>
    @endif
</div>