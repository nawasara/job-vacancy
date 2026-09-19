<?php

namespace Nawasara\JobVacancy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\JobVacancy\Support\EditorJsRenderer;

/**
 * Allow-list resource for job vacancy data from the local DB snapshot.
 *
 * Deliberately BLOCKED and why:
 *   - `source_id`  — internal upstream UUID; `slug` is the public identity.
 *   - `is_active`  — internal Nawasara snapshot status.
 *   - `synced_at`  — internal sync trace, not business data.
 *   - `extern_*`   — storage tech columns; the public sees `created_at`/`updated_at`.
 *   - `time`/`version` editor.js — internal editor artifacts, useless to clients.
 *
 * `job_description` is delivered as ALREADY SANITIZED HTML — clients may render
 * it as safe rich text, never double-render raw.
 */
class JobVacancyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug'                => $this->slug,
            'job_title'           => $this->job_title,
            'job_description'     => EditorJsRenderer::sanitizeHtml((string) $this->job_description),
            'company_name'        => $this->company_name,
            'company_description' => $this->company_description,
            'company_url'         => $this->company_url,
            'company_address'     => $this->company_address,
            'location'            => $this->location,
            'salary'              => $this->salary,
            'type'                => $this->type,
            'category'            => $this->category,
            'apply_url'           => $this->apply_url,
            'requirements'        => $this->normalizeEditorJs($this->requirements),
            'expires_at'          => $this->iso8601($this->expires_at),
            'is_expired'          => (bool) $this->is_expired,
            'created_at'          => $this->iso8601($this->extern_created_at),
            'updated_at'          => $this->iso8601($this->extern_updated_at),
        ];
    }

    /**
     * Normalize the editor.js structure.
     * Block whitelist: paragraph, header, list. Other blocks are dropped.
     * Never use {!! !!} in Blade — render manually per block.
     *
     * @param  mixed  $raw
     * @return array{time: int, blocks: array<int, array{type: string, data: array}>}|null
     */
    protected function normalizeEditorJs(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $allowedTypes = ['paragraph', 'header', 'list'];
        $blocks = [];

        foreach ($raw['blocks'] ?? [] as $block) {
            $type = $block['type'] ?? '';

            if (! in_array($type, $allowedTypes, true)) {
                continue;
            }

            $data = $block['data'] ?? [];

            // paragraph: text must be a string
            if ($type === 'paragraph') {
                $data['text'] = strip_tags($data['text'] ?? '');
            }

            // header: text + level
            if ($type === 'header') {
                $data['text']  = strip_tags($data['text'] ?? '');
                $data['level'] = max(1, min(6, (int) ($data['level'] ?? 2)));
            }

            // list: items must be an array, safe style.
            // Upstream items are objects {meta, items, content} — take content,
            // fall back to the plain string when the item really is a string.
            if ($type === 'list') {
                $data['items'] = array_map(
                    fn ($item) => strip_tags(
                        is_string($item)
                            ? $item
                            : (string) ($item['content'] ?? $item['text'] ?? ''),
                    ),
                    (array) ($data['items'] ?? []),
                );
                $data['style'] = in_array($data['style'] ?? '', ['ordered', 'unordered'], true)
                    ? $data['style']
                    : 'unordered';
            }

            $blocks[] = [
                'type' => $type,
                'data' => $data,
            ];
        }

        if ($blocks === []) {
            return null;
        }

        return [
            'time'   => (int) ($raw['time'] ?? 0),
            'blocks' => $blocks,
        ];
    }

    protected function iso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->toIso8601String();
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}