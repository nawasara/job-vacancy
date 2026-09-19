<div>
    <x-nawasara-ui::page.container>
        {{-- Page header --}}
        <x-nawasara-ui::page-header
            title="Job Vacancies"
            description="List of job vacancies from the upstream service. Read-only data from the service management platform."
            :count="$this->jobVacancies->total().' vacancies'">
        </x-nawasara-ui::page-header>

        {{-- Toolbar filter — search + filter dropdown + per-page + reset --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- Search — wire:model with debounce so the sanitized query only
                runs 300ms after the user stops typing --}}
            <div class="relative flex-1 min-w-48">
                <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-3.5">
                    <x-lucide-search class="shrink-0 size-4 text-gray-400 dark:text-neutral-500" />
                </div>
                <input type="search"
                    wire:model.live.debounce.300ms="q"
                    placeholder="Search job title or company..."
                    autocomplete="off"
                    aria-label="Search job vacancies"
                    class="py-2.5 ps-10 pe-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-emerald-600 focus:ring-emerald-600 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" />
            </div>

            <x-nawasara-ui::filter-dropdown label="Location" :items="$this->locationOptions" model="location" />
            <x-nawasara-ui::filter-dropdown label="Type" :items="$this->typeOptions" model="type" />
            <x-nawasara-ui::filter-dropdown label="Category" :items="$this->categoryOptions" model="category" />

            <select wire:model.live="perPage"
                aria-label="Per page"
                class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                <option value="4">4</option>
                <option value="8">8</option>
                <option value="12">12</option>
                <option value="24">24</option>
            </select>

            @if ($q !== '' || $location !== '' || $type !== '' || $category !== '')
                <x-nawasara-ui::button variant="ghost" size="sm" wire:click="clearFilters">
                    Reset filters
                </x-nawasara-ui::button>
            @endif
        </div>

        @if ($this->jobVacancies->isEmpty())
            <x-nawasara-ui::empty-state icon="lucide-briefcase" title="No job vacancies"
                description="No vacancy matches these filters, or the upstream credentials are not configured in Vault.">
            </x-nawasara-ui::empty-state>
        @else
            {{-- Table of job vacancies --}}
            <x-nawasara-ui::table
                :headers="['Job', 'Location', 'Type', 'Category', 'Expires', 'Salary', '']"
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
                                {{ $item->location ?: '—' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400">
                                {{ $item->type ?: '—' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400">
                                {{ $item->category ?: '—' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400">
                                {{ $item->expires_at?->format('d M Y') ?: '—' }}
                            </td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-neutral-100">
                                {{ $item->salary ?: '—' }}
                            </td>
                            <td class="px-6 py-3 text-right">
                                <x-nawasara-ui::button size="sm" color="secondary" variant="outline"
                                    wire:click.stop="openDetail('{{ $item->slug }}')">
                                    Details
                                </x-nawasara-ui::button>
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
            :title="$detail['job_title'] ?? 'Job Vacancy Details'"
            :subtitle="isset($detail['company']) ? ($detail['company'].' · '.($detail['location'] ?: 'Location not available')) : ''"
            maxWidth="lg">
            <div wire:loading.flex wire:target="detail" class="justify-center py-10">
                <x-nawasara-ui::loading />
            </div>

            <div wire:loading.remove wire:target="detail">
                @if ($detailHtml === '')
                    <p class="text-sm text-gray-500 dark:text-neutral-500">
                        Select a vacancy to see details.
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
                        View full details
                    </x-nawasara-ui::button>
                @endif
                <x-nawasara-ui::button size="sm" color="secondary" @click="close()">
                    Close
                </x-nawasara-ui::button>
            </x-slot:footer>
        </x-nawasara-ui::modal>
    </x-nawasara-ui::page.container>
</div>