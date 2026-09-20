<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="{{ $this->jobVacancy->job_title }}"
            description="{{ $this->jobVacancy->company_name }} · {{ $this->jobVacancy->location ?: 'Lokasi tidak tersedia' }}">

            <x-nawasara-ui::button variant="ghost" size="sm" color="secondary"
                :href="route('nawasara-job-vacancy.job-vacancy.index')" wire:navigate.hover>
                Kembali
            </x-nawasara-ui::button>

            @php
                $applyUrl = $this->jobVacancy->apply_url ?? '';
            @endphp
            @if (filter_var($applyUrl, FILTER_VALIDATE_URL))
                <x-nawasara-ui::button color="primary" size="sm" :href="$applyUrl" target="_blank">
                    Lamar Sekarang
                </x-nawasara-ui::button>
            @endif
        </x-nawasara-ui::page-header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Rincian utama --}}
            <div class="space-y-6 lg:col-span-2">
                <x-nawasara-ui::page.card title="Deskripsi Pekerjaan">
                    <div class="space-y-3 text-sm leading-relaxed text-gray-600 dark:text-neutral-400">
                        {!! nl2br(e($this->jobVacancy->job_description ?: '-')) !!}
                    </div>
                </x-nawasara-ui::page.card>

                <x-nawasara-ui::page.card title="Persyaratan &amp; Kualifikasi">
                    @if (is_null($this->jobVacancy->requirements))
                        <p class="text-sm text-gray-500 dark:text-neutral-500">Persyaratan tidak dituliskan.</p>
                    @else
                        <x-nawasara-job-vacancy::editorjs-renderer :content="$this->jobVacancy->requirements" />
                    @endif
                </x-nawasara-ui::page.card>
            </div>

            {{-- Keterangan perusahaan --}}
            <div class="space-y-6">
                <x-nawasara-ui::page.card title="Informasi">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-neutral-500">Perusahaan</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-neutral-100">
                                {{ $this->jobVacancy->company_name }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-neutral-500">Lokasi</dt>
                            <dd class="text-right text-gray-900 dark:text-neutral-100">
                                {{ $this->jobVacancy->location ?: '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-neutral-500">Alamat</dt>
                            <dd class="text-right text-gray-900 dark:text-neutral-100">
                                {{ $this->jobVacancy->company_address ?: '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-neutral-500">Tipe</dt>
                            <dd class="text-right text-gray-900 dark:text-neutral-100">
                                {{ $this->jobVacancy->type ?: '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-neutral-500">Kategori</dt>
                            <dd class="text-right text-gray-900 dark:text-neutral-100">
                                {{ $this->jobVacancy->category ?: '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-neutral-500">Gaji</dt>
                            <dd class="text-right font-semibold text-gray-900 dark:text-neutral-100">
                                {{ $this->jobVacancy->salary ?: '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-neutral-500">Berakhir</dt>
                            <dd class="text-right text-gray-900 dark:text-neutral-100">
                                {{ $this->jobVacancy->expires_at ?: '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500 dark:text-neutral-500">Status</dt>
                            <dd class="text-right">
                                @if ($this->jobVacancy->is_expired)
                                    <x-nawasara-ui::badge color="neutral">Kedaluwarsa</x-nawasara-ui::badge>
                                @else
                                    <x-nawasara-ui::badge color="success" icon="lucide-circle-check">Aktif</x-nawasara-ui::badge>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </x-nawasara-ui::page.card>

                @php
                    $companyUrl = $this->jobVacancy->company_url ?? '';
                @endphp
                @if (filter_var($companyUrl, FILTER_VALIDATE_URL))
                    <x-nawasara-ui::button color="secondary" size="sm"
                        :href="$companyUrl" target="_blank" class="w-full">
                        Kunjungi Situs Perusahaan
                    </x-nawasara-ui::button>
                @endif
            </div>
        </div>
    </x-nawasara-ui::page.container>
</div>