<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Rakaca', 'url' => '#'], ['label' => 'Lowongan Kerja']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Lowongan Kerja"
            description="Lowongan kerja yang ditarik dari Rakaca. Data hanya dibaca, perubahannya dilakukan di Rakaca."
            :count="$this->jobVacancies->total().' lowongan'">
            {{-- Zona aksi kanan. Tombol sinkronisasi ada DI SINI, bukan di
                 toolbar saringan: ia mengubah data, sedangkan toolbar hanya
                 mengubah tampilan. --}}
            <x-nawasara-ui::icon-button icon="refresh-cw"
                tooltip="Tarik ulang dari Rakaca sekarang"
                wire:click="syncNow" loadingTarget="syncNow" placement="left" />
        </x-nawasara-ui::page-header>

        {{-- Kapan datanya terakhir disegarkan. Tanpa ini staf tidak punya cara
             membedakan "Rakaca memang sedang sepi" dari "sinkronisasinya mati
             sejak tiga hari lalu", dan keduanya terlihat persis sama. --}}
        <x-nawasara-ui::sync-info-bar :lastSyncedAt="$this->lastSyncedAt" />

        {{-- Toolbar, the shape every list page in Nawasara uses (AGENTS.md §1a).

             One filter-panel rather than a row of standalone filter-dropdowns:
             separate dropdowns eat the toolbar width, leave no room for an
             action button, and give the operator no single place to see what is
             currently filtered. The panel teleports its chips into
             [data-filter-chips] below, which is why that div is not optional. --}}
        <div class="space-y-2 mb-4">
            <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <x-nawasara-ui::filter-panel
                        label="Saring"
                        :state="['location' => $location, 'type' => $type, 'category' => $category]"
                        :labels="[
                            'location' => $this->locationOptions,
                            'type' => $this->typeOptions,
                            'category' => $this->categoryOptions,
                        ]">
                        <x-nawasara-ui::filter-group label="Lokasi" model="location"
                            :items="$this->locationOptions" icon="lucide-map-pin" />
                        <x-nawasara-ui::filter-group label="Tipe" model="type"
                            :items="$this->typeOptions" icon="lucide-clock" />
                        <x-nawasara-ui::filter-group label="Kategori" model="category"
                            :items="$this->categoryOptions" icon="lucide-tag" />
                    </x-nawasara-ui::filter-panel>
                </div>

                <x-nawasara-ui::search-input model="q" placeholder="Cari nama pekerjaan atau perusahaan..." />
            </div>

            {{-- Required. filter-panel teleports its chips here; without this
                 div the chips vanish and nobody can tell what is filtered. --}}
            <div data-filter-chips class="flex flex-wrap items-center gap-2"></div>
        </div>

        @if ($this->jobVacancies->isEmpty())
            {{-- Two empty states, not one. A single message sends people looking
                 for data that is there, just filtered out. --}}
            @if ($q !== '' || $location !== '' || $type !== '' || $category !== '')
                <x-nawasara-ui::empty-state icon="lucide-search-x"
                    title="Tidak ada yang cocok"
                    description="Tidak ada lowongan yang cocok dengan pencarian atau saringan ini. Ubah kata kuncinya, atau bersihkan saringan.">
                    <x-nawasara-ui::button size="sm" color="secondary" wire:click="clearFilters">
                        Bersihkan saringan
                    </x-nawasara-ui::button>
                </x-nawasara-ui::empty-state>
            @else
                <x-nawasara-ui::empty-state icon="lucide-briefcase"
                    title="Belum ada lowongan"
                    description="Belum ada yang ditarik dari Rakaca. Periksa kredensial Rakaca di Vault, lalu jalankan sinkronisasi." />
            @endif
        @else
            <x-nawasara-ui::table
                :headers="['Pekerjaan', 'Lokasi', 'Tipe', 'Kategori', 'Berakhir', 'Gaji', '']"
                stickyLast>
                <x-slot:table>
                    @foreach ($this->jobVacancies as $item)
                        <tr wire:key="job-vacancy-{{ $item->slug }}"
                            class="cursor-pointer"
                            wire:click="openDetail('{{ $item->slug }}')">
                            <td class="px-6 py-3">
                                <p class="font-semibold text-gray-900 dark:text-neutral-100">
                                    {{ $item->job_title }}
                                </p>
                                <p class="text-sm text-emerald-700 dark:text-emerald-400">
                                    {{ $item->company_name }}
                                </p>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400">
                                {{ $item->location ?: '-' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400">
                                {{ $item->type ?: '-' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400">
                                {{ $item->category ?: '-' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400">
                                {{ $item->expires_at?->format('d M Y') ?: '-' }}
                            </td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-neutral-100">
                                {{ $item->salary ?: '-' }}
                            </td>
                            <td class="px-6 py-3 text-right" wire:click.stop>
                                {{-- Three-dot menu, not a row of buttons. Inline
                                     buttons eat the column width and leave no
                                     room for a third action later, which is how
                                     pages end up quietly losing one. --}}
                                <x-nawasara-ui::dropdown-menu-action :id="$item->slug" :items="[
                                    ['type' => 'click', 'label' => 'Pratinjau',
                                     'wire:click' => 'openDetail(\''.$item->slug.'\')',
                                     'icon' => 'lucide-eye', 'permission' => 'job.vacancy.view'],
                                    ['type' => 'link', 'label' => 'Buka halaman lengkap',
                                     'href' => route('nawasara-job-vacancy.job-vacancy.show', $item->slug),
                                     'icon' => 'lucide-external-link', 'permission' => 'job.vacancy.view'],
                                ]" />
                            </td>
                        </tr>
                    @endforeach
                </x-slot:table>
            </x-nawasara-ui::table>

            <div class="mt-6">
                {{ $this->jobVacancies->links('nawasara-ui::components.pagination') }}
            </div>
        @endif

        {{-- Modal preview — compact content rendered server-side via Index::openDetail();
            full detail opens from the "View full details" button. --}}
        <x-nawasara-ui::modal id="job-vacancy-detail"
            :title="$detail['job_title'] ?? 'Rincian Lowongan'"
            :subtitle="isset($detail['company']) ? ($detail['company'].' · '.($detail['location'] ?: 'Lokasi tidak tersedia')) : ''"
            maxWidth="lg">
            <div wire:loading.flex wire:target="detail" class="justify-center py-10">
                <x-nawasara-ui::loading />
            </div>

            <div wire:loading.remove wire:target="detail">
                @if ($detailHtml === '')
                    <p class="text-sm text-gray-500 dark:text-neutral-500">
                        Pilih satu lowongan untuk melihat rinciannya.
                    </p>
                @else
                    {!! $detailHtml !!}
                @endif
            </div>

            <x-slot:footer>
                @if (! empty($detail['slug']))
                    <x-nawasara-ui::button size="sm"
                        :href="route('nawasara-job-vacancy.job-vacancy.show', $detail['slug'])"
                        wire:navigate.hover>
                        Lihat selengkapnya
                    </x-nawasara-ui::button>
                @endif
                <x-nawasara-ui::button size="sm" color="secondary" @click="close()">
                    Tutup
                </x-nawasara-ui::button>
            </x-slot:footer>
        </x-nawasara-ui::modal>
    </x-nawasara-ui::page.container>
</div>
