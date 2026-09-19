{{--
    editorjs-renderer — render EditorJS blocks into safe HTML (no client JS).

    Usage:
        <x-nawasara-job-vacancy::editorjs-renderer :content="$jobVacancy->requirements" />

    `content` accepts full EditorJS data (`{ time, blocks: [...] }`), a plain block
    array, OR a JSON string. Text is rendered through `EditorJsRenderer::sanitizeHtml()`
    (tag-whitelist, safe attributes, strips `script`/event handlers/`javascript:`),
    not raw-escaped — so upstream markup (`<b>`, `<a>`, etc.) shows formatted.
    Unknown blocks render as a paragraph (fallback). Supported types:
        header, paragraph, list (unordered/ordered/checklist), checklist, quote, code,
        table (+ withHeadings, action link column), image (stretched/border/background),
        linktool, warning, embed (http(s) only), attaches, raw.
--}}
@props([
    'content' => null,
    'showEmpty' => false,
])

@php
    use Nawasara\JobVacancy\Support\EditorJsRenderer;

    $blocks = [];
    if (is_string($content)) {
        $decoded = json_decode($content, true);
        $blocks = is_array($decoded) ? ($decoded['blocks'] ?? $decoded) : [];
    } elseif (is_array($content)) {
        $blocks = isset($content['blocks']) && is_array($content['blocks'])
            ? $content['blocks']
            : $content;
    }

    $linkExcerpt = static function (mixed $cell): array {
        $cell = (string) $cell;
        if (preg_match('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $cell, $m)) {
            $href  = EditorJsRenderer::sanitizeUrl($m[1]);
            $label = trim(strip_tags($m[2])) !== '' ? trim(strip_tags($m[2])) : 'Download';
            return ['has' => $href !== '#', 'href' => $href, 'label' => $label];
        }
        return ['has' => false, 'href' => '#', 'label' => 'Download'];
    };
@endphp

@if (empty($blocks))
    @if ($showEmpty)
        <p class="text-sm text-gray-500 dark:text-neutral-500">No content.</p>
    @endif
@else
    <div class="space-y-3">
        @foreach ($blocks as $block)
            @php
                $type = (string) ($block['type'] ?? 'paragraph');
                $data = $block['data'] ?? [];
            @endphp

            @switch($type)
                @case('header')
                    @php
                        $level = (int) ($data['level'] ?? 2);
                        $level = max(1, min(6, $level));
                        $headerClass = $level === 1
                            ? 'text-2xl'
                            : ($level === 2 ? 'text-xl' : ($level === 3 ? 'text-lg' : 'text-base'));
                    @endphp
                    @if ($level === 1)
                        <h1 class="font-semibold text-gray-900 dark:text-neutral-100 {{ $headerClass }}">{!! EditorJsRenderer::sanitizeHtml((string) ($data['text'] ?? '')) !!}</h1>
                    @elseif ($level === 2)
                        <h2 class="font-semibold text-gray-900 dark:text-neutral-100 {{ $headerClass }}">{!! EditorJsRenderer::sanitizeHtml((string) ($data['text'] ?? '')) !!}</h2>
                    @elseif ($level === 3)
                        <h3 class="font-semibold text-gray-900 dark:text-neutral-100 {{ $headerClass }}">{!! EditorJsRenderer::sanitizeHtml((string) ($data['text'] ?? '')) !!}</h3>
                    @elseif ($level === 4)
                        <h4 class="font-semibold text-gray-900 dark:text-neutral-100 {{ $headerClass }}">{!! EditorJsRenderer::sanitizeHtml((string) ($data['text'] ?? '')) !!}</h4>
                    @elseif ($level === 5)
                        <h5 class="font-semibold text-gray-900 dark:text-neutral-100 {{ $headerClass }}">{!! EditorJsRenderer::sanitizeHtml((string) ($data['text'] ?? '')) !!}</h5>
                    @else
                        <h6 class="font-semibold text-gray-900 dark:text-neutral-100 {{ $headerClass }}">{!! EditorJsRenderer::sanitizeHtml((string) ($data['text'] ?? '')) !!}</h6>
                    @endif
                    @break

                @case('paragraph')
                    @php $text = (string) ($data['text'] ?? ''); @endphp
                    @if (trim($text) !== '')
                        <p class="text-sm leading-relaxed text-gray-600 dark:text-neutral-400">{!! EditorJsRenderer::sanitizeHtml($text) !!}</p>
                    @endif
                    @break

                @case('list')
                    @php
                        $style = $data['style'] ?? 'unordered';
                        $items = $data['items'] ?? [];
                    @endphp

                    @if ($style === 'checklist')
                        <ul class="space-y-1.5 text-sm text-gray-600 dark:text-neutral-400">
                            @foreach ($items as $item)
                                @php
                                    $entry = is_array($item)
                                        ? $item
                                        : ['text' => $item, 'checked' => false];
                                    $checked = (bool) ($entry['meta']['checked'] ?? $entry['checked'] ?? false);
                                    $text    = (string) ($entry['content'] ?? $entry['text'] ?? '');
                                @endphp
                                <li class="flex items-start gap-2">
                                    @if ($checked)
                                        <x-lucide-circle-check class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    @else
                                        <x-lucide-circle class="mt-0.5 size-4 shrink-0 text-gray-300 dark:text-neutral-600" />
                                    @endif
                                    <span>{!! EditorJsRenderer::sanitizeHtml($text) !!}</span>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($style === 'ordered')
                        <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-600 dark:text-neutral-400">
                            @foreach ($items as $item)
                                @php
                                    $itemText = is_array($item)
                                        ? (string) ($item['content'] ?? $item['text'] ?? '')
                                        : (string) $item;
                                @endphp
                                <li>{!! EditorJsRenderer::sanitizeHtml($itemText) !!}</li>
                            @endforeach
                        </ol>
                    @else
                        <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-neutral-400">
                            @foreach ($items as $item)
                                @php
                                    $itemText = is_array($item)
                                        ? (string) ($item['content'] ?? $item['text'] ?? '')
                                        : (string) $item;
                                @endphp
                                <li>{!! EditorJsRenderer::sanitizeHtml($itemText) !!}</li>
                            @endforeach
                        </ul>
                    @endif
                    @break

                @case('checklist')
                    <ul class="space-y-1.5 text-sm text-gray-600 dark:text-neutral-400">
                        @foreach ($data['items'] ?? [] as $item)
                            @php
                                $entry = is_array($item) ? $item : ['text' => $item, 'checked' => false];
                            @endphp
                            <li class="flex items-start gap-2">
                                @if (! empty($entry['checked']))
                                    <x-lucide-circle-check class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                @else
                                    <x-lucide-circle class="mt-0.5 size-4 shrink-0 text-gray-300 dark:text-neutral-600" />
                                @endif
                                <span>{!! EditorJsRenderer::sanitizeHtml((string) ($entry['text'] ?? '')) !!}</span>
                            </li>
                        @endforeach
                    </ul>
                    @break

                @case('quote')
                    <blockquote class="border-l-4 border-emerald-600 pl-4 text-sm italic text-gray-600 dark:text-neutral-400">
                        {!! EditorJsRenderer::sanitizeHtml((string) ($data['text'] ?? '')) !!}
                        @if (! empty($data['caption']))
                            <footer class="mt-1 text-xs not-italic text-gray-400 dark:text-neutral-500">
                                — {!! EditorJsRenderer::sanitizeHtml((string) $data['caption']) !!}
                            </footer>
                        @endif
                    </blockquote>
                    @break

                @case('code')
                    <pre class="overflow-x-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-800 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200"><code>{{ $data['code'] ?? ($data['text'] ?? '') }}</code></pre>
                    @break

                @case('table')
                    @php
                        $rows           = $data['content'] ?? [];
                        $withHeadings   = ! empty($data['withHeadings']);
                        $stretched      = ! empty($data['stretched']);
                        $lastColIndex   = count($rows[0] ?? []) - 1;
                        $cellIsLink     = static fn (mixed $cell): bool => (bool) preg_match('/<a\s[^>]*href/i', (string) $cell);
                        $renderCell     = static function (mixed $cell) use ($cellIsLink, $linkExcerpt): string {
                            if ($cellIsLink($cell)) {
                                $ex = $linkExcerpt($cell);
                                if ($ex['has']) {
                                    return '<a href="' . $ex['href'] . '" target="_blank" rel="noopener noreferrer" '
                                        . 'class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-400">'
                                        . e($ex['label']) . '</a>';
                                }
                            }
                            $cell = (string) $cell;
                            return $cell === '' ? '&nbsp;' : EditorJsRenderer::sanitizeHtml($cell);
                        };
                    @endphp
                    @if (! empty($rows) && is_array($rows))
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-neutral-700 {{ $stretched ? '' : 'w-fit max-w-full' }}">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-neutral-700">
                                @if ($withHeadings)
                                    <thead class="border-b border-gray-200 bg-gray-50 text-left dark:border-neutral-700 dark:bg-neutral-800/50">
                                        <tr class="divide-x divide-gray-200 dark:divide-neutral-700">
                                            @foreach ($rows[0] as $cell)
                                                <th class="px-3 py-2 text-xs font-semibold text-gray-700 dark:text-neutral-300">{!! EditorJsRenderer::sanitizeHtml((string) $cell) !!}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                @endif
                                <tbody>
                                    @foreach ($withHeadings ? array_slice($rows, 1) : $rows as $row)
                                        <tr class="divide-x divide-gray-200 dark:divide-neutral-700">
                                            @foreach ($row as $colIdx => $cell)
                                                <td class="px-3 py-2 align-top {{ $colIdx === $lastColIndex ? 'text-right' : '' }}">
                                                    {!! $renderCell($cell) !!}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @break

                @case('image')
                    @php
                        $imageUrl       = $data['file']['url'] ?? ($data['url'] ?? '');
                        $caption        = (string) ($data['caption'] ?? '');
                        $stretched      = ! empty($data['stretched']);
                        $withBorder     = ! empty($data['withBorder']);
                        $withBackground = ! empty($data['withBackground']);
                    @endphp
                    @if (EditorJsRenderer::sanitizeUrl($imageUrl) !== '#')
                        <figure class="space-y-2">
                            <div class="overflow-hidden rounded-lg {{ $stretched ? 'w-full' : 'w-fit max-w-full' }} {{ $withBorder ? 'border border-gray-200 dark:border-neutral-700' : '' }} {{ $withBackground ? 'bg-gray-100 p-2 dark:bg-neutral-800' : '' }}">
                                <img src="{{ e(EditorJsRenderer::sanitizeUrl($imageUrl)) }}" alt="{{ $caption }}"
                                    class="h-auto max-w-full {{ $stretched ? 'w-full' : '' }}" loading="lazy" decoding="async">
                            </div>
                            @if ($caption !== '')
                                <figcaption class="text-xs text-gray-400 dark:text-neutral-500">{!! EditorJsRenderer::sanitizeHtml($caption) !!}</figcaption>
                            @endif
                        </figure>
                    @endif
                    @break

                @case('linktool')
                    @php
                        $link      = (string) ($data['link'] ?? '');
                        $meta      = $data['meta'] ?? [];
                        $linkTitle = (string) ($meta['title'] ?? $link);
                        $desc      = (string) ($meta['description'] ?? '');
                        $image     = is_array($meta['image'] ?? null)
                            ? (string) ($meta['image']['url'] ?? '')
                            : (string) ($meta['image'] ?? '');
                        $host      = $link !== '' ? (string) parse_url($link, PHP_URL_HOST) : '';
                    @endphp
                    @if (EditorJsRenderer::sanitizeUrl($link) !== '#')
                        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white transition-colors hover:border-emerald-600/40 dark:border-neutral-700 dark:bg-neutral-800">
                            <a href="{{ EditorJsRenderer::sanitizeUrl($link) }}" target="_blank" rel="nofollow noopener" class="group flex flex-col sm:flex-row">
                                @if ($image !== '' && EditorJsRenderer::sanitizeUrl($image) !== '#')
                                    <img src="{{ e(EditorJsRenderer::sanitizeUrl($image)) }}" alt="{{ $linkTitle }}"
                                        class="h-40 w-full object-cover sm:h-auto sm:w-48 sm:shrink-0"
                                        loading="lazy" decoding="async">
                                @endif
                                <span class="flex grow flex-col justify-center p-3">
                                    <span class="line-clamp-1 text-sm font-semibold text-gray-900 dark:text-neutral-100">{{ $linkTitle }}</span>
                                    @if ($desc !== '')
                                        <span class="mt-0.5 line-clamp-2 text-xs text-gray-500 dark:text-neutral-400">{{ $desc }}</span>
                                    @endif
                                    <span class="mt-2 flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        <x-lucide-external-link class="size-3.5 shrink-0" />
                                        {{ $host !== '' ? $host : $link }}
                                    </span>
                                </span>
                            </a>
                        </div>
                    @endif
                    @break

                @case('warning')
                    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-700/60 dark:bg-amber-900/20">
                        <div class="flex gap-2.5">
                            <x-lucide-alert-triangle class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <div class="text-sm">
                                @if (! empty($data['title']))
                                    <h4 class="font-semibold text-amber-800 dark:text-amber-200">{!! EditorJsRenderer::sanitizeHtml((string) $data['title']) !!}</h4>
                                @endif
                                @if (! empty($data['message']))
                                    <p class="mt-0.5 text-amber-700 dark:text-amber-300">{!! EditorJsRenderer::sanitizeHtml((string) $data['message']) !!}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @break

                @case('embed')
                    @php
                        $embedUrl     = trim((string) ($data['embed'] ?? ''));
                        $embedCaption = (string) ($data['caption'] ?? '');
                    @endphp
                    @if ($embedUrl !== '' && preg_match('#^https?://#i', $embedUrl))
                        <figure class="space-y-2">
                            <div class="relative overflow-hidden rounded-lg" style="padding-bottom: 56.25%;">
                                <iframe src="{{ e($embedUrl) }}"
                                    class="absolute top-0 left-0 h-full w-full"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen>
                                </iframe>
                            </div>
                            @if ($embedCaption !== '')
                                <figcaption class="text-xs text-gray-400 dark:text-neutral-500">{!! EditorJsRenderer::sanitizeHtml($embedCaption) !!}</figcaption>
                            @endif
                        </figure>
                    @endif
                    @break

                @case('attaches')
                    @php
                        $file      = $data['file'] ?? [];
                        $fileUrl   = EditorJsRenderer::sanitizeUrl($file['url'] ?? null);
                        $fileTitle = (string) ($data['title'] ?: ($file['name'] ?? 'Download File'));
                        $fileSize  = isset($file['size'])
                            ? number_format((float) $file['size'] / 1024, 2, '.', '') . ' KB'
                            : '';
                    @endphp
                    @if ($fileUrl !== '#')
                        <a href="{{ $fileUrl }}" download
                            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 transition-colors hover:border-emerald-600/40 dark:border-neutral-700 dark:bg-neutral-800">
                            <x-lucide-file-text class="size-6 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span class="grow">
                                <span class="block text-sm font-medium text-gray-900 dark:text-neutral-100">{{ $fileTitle }}</span>
                                @if ($fileSize !== '')
                                    <span class="block text-xs text-gray-500 dark:text-neutral-400">{{ $fileSize }}</span>
                                @endif
                            </span>
                            <x-lucide-download class="size-4 shrink-0 text-gray-400 dark:text-neutral-500" />
                        </a>
                    @endif
                    @break

                @case('raw')
                    @php $rawHtml = (string) ($data['html'] ?? ''); @endphp
                    @if (trim($rawHtml) !== '')
                        <div class="text-sm text-gray-600 dark:text-neutral-400">{!! EditorJsRenderer::sanitizeHtml($rawHtml) !!}</div>
                    @endif
                    @break

                @default
                    @if (($data['text'] ?? '') !== '')
                        <p class="text-sm text-gray-600 dark:text-neutral-400">{!! EditorJsRenderer::sanitizeHtml((string) $data['text']) !!}</p>
                    @endif
            @endswitch
        @endforeach
    </div>
@endif