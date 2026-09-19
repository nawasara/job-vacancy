<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use Tests\TestCase;

class EditorjsRendererTest extends TestCase
{
    private function render(mixed $content = null, bool $showEmpty = false): string
    {
        $html = view('nawasara-job-vacancy::components.editorjs-renderer', [
            'content' => $content,
            'showEmpty' => $showEmpty,
        ])->render();

        return trim($html);
    }

    public function test_renders_header_by_level(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'header', 'data' => ['level' => 3, 'text' => 'Main Requirements']],
            ],
        ]);

        $this->assertStringContainsString('<h3', $html);
        $this->assertStringContainsString('Main Requirements', $html);
    }

    public function test_renders_ordered_and_unordered_lists(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'list', 'data' => ['style' => 'ordered', 'items' => ['Item One', 'Item Two']]],
                ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['Plain']]],
            ],
        ]);

        $this->assertStringContainsString('<ol', $html);
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('Item One', $html);
        $this->assertStringContainsString('Plain', $html);
    }

    public function test_renders_checklist_items(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'checklist', 'data' => ['items' => [
                    ['text' => 'Checked', 'checked' => true],
                    ['text' => 'Unchecked', 'checked' => false],
                ]]],
            ],
        ]);

        $this->assertStringContainsString('Checked', $html);
        $this->assertStringContainsString('Unchecked', $html);
        $this->assertStringContainsString('text-emerald-600', $html);
        $this->assertStringContainsString('text-gray-300', $html);
    }

    public function test_accepts_raw_block_array(): void
    {
        $html = $this->render([
            ['type' => 'paragraph', 'data' => ['text' => 'Plain text']],
        ]);

        $this->assertStringContainsString('Plain text', $html);
    }

    public function test_empty_content_renders_nothing_or_placeholder(): void
    {
        $this->assertSame('', $this->render(null));
        $this->assertSame('', $this->render(['blocks' => []]));

        $this->assertStringContainsString('No content.', $this->render(null, showEmpty: true));
    }

    public function test_strips_script_from_text_blocks(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'paragraph', 'data' => ['text' => '<script>alert(1)</script>']],
            ],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_list_items_accepts_upstream_object_shape(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => [
                    ['meta' => [], 'items' => [], 'content' => 'Link: <b><a href="x">bit.ly/apply</a></b>'],
                    ['meta' => [], 'items' => [], 'content' => 'Email: recruitment@example.com'],
                ]]],
            ],
        ]);

        $this->assertStringContainsString('Link:', $html);
        $this->assertStringContainsString('Email: recruitment@example.com', $html);
        $this->assertStringNotContainsString('<a href="x">', $html);
    }

    public function test_unknown_block_type_falls_back_to_paragraph(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'weird-tool', 'data' => ['text' => 'Fallback text']],
            ],
        ]);

        $this->assertStringContainsString('Fallback text', $html);
    }

    public function test_list_checklist_style_handles_upstream_shape(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'list', 'data' => ['style' => 'checklist', 'items' => [
                    ['meta' => ['checked' => true], 'content' => 'Active'],
                    ['meta' => ['checked' => false], 'content' => 'Inactive'],
                ]]],
            ],
        ]);

        $this->assertStringContainsString('Active', $html);
        $this->assertStringContainsString('Inactive', $html);
        $this->assertStringContainsString('text-emerald-600', $html);
    }

    public function test_accepts_string_json_content(): void
    {
        $html = $this->render(json_encode([
            'blocks' => [
                ['type' => 'header', 'data' => ['level' => 2, 'text' => 'Title from JSON']],
            ],
        ]));

        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('Title from JSON', $html);
    }

    public function test_renders_linktool_card(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'linktool', 'data' => [
                    'link' => 'https://example.com/article',
                    'meta' => ['title' => 'Useful Link', 'description' => 'Short description'],
                ]],
            ],
        ]);

        $this->assertStringContainsString('https://example.com/article', $html);
        $this->assertStringContainsString('Useful Link', $html);
        $this->assertStringContainsString('example.com', $html);
    }

    public function test_linktool_rejects_javascript_href(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'linktool', 'data' => ['link' => 'javascript:alert(1)']],
            ],
        ]);

        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_renders_warning_block(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'warning', 'data' => ['title' => 'Attention', 'message' => 'Deadline <b>today</b>']],
            ],
        ]);

        $this->assertStringContainsString('Attention', $html);
        $this->assertStringContainsString('Deadline', $html);
        $this->assertStringContainsString('<b>today</b>', $html);
    }

    public function test_renders_table_with_headings_and_sanitized_action_link(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'table', 'data' => [
                    'withHeadings' => true,
                    'content' => [
                        ['Name', 'Action'],
                        ['Registration', '<a href="https://example.com/download">Download File</a>'],
                        ['Note', '<a href="javascript:alert(1)">Evil</a>'],
                        ['HTML', '<b>bold</b>'],
                    ],
                ]],
            ],
        ]);

        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('href="https://example.com/download"', $html);
        $this->assertStringContainsString('Download File', $html);
        $this->assertStringContainsString('bg-emerald-600', $html);
        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        $this->assertStringContainsString('<b>bold</b>', $html);
    }

    public function test_embed_block_only_renders_https_urls(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'embed', 'data' => ['embed' => 'https://www.youtube.com/embed/abcdef', 'caption' => 'Video']],
                ['type' => 'embed', 'data' => ['embed' => 'javascript:alert(1)']],
            ],
        ]);

        $this->assertStringContainsString('https://www.youtube.com/embed/abcdef', $html);
        $this->assertStringContainsString('Video', $html);
        $this->assertStringNotContainsString('javascript:alert(1)', $html);
    }

    public function test_renders_attaches_download_card(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'attaches', 'data' => [
                    'title' => 'Requirements File',
                    'file' => ['url' => 'https://example.com/b.pdf', 'size' => 1048576],
                ]],
            ],
        ]);

        $this->assertStringContainsString('Requirements File', $html);
        $this->assertStringContainsString('1024.00 KB', $html);
        $this->assertStringContainsString('download', $html);
    }

    public function test_raw_block_html_is_sanitized(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'raw', 'data' => ['html' => '<script>alert(1)</script><b>Bold</b>']],
            ],
        ]);

        $this->assertStringContainsString('<b>Bold</b>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_renders_upstream_inline_html_in_list_items(): void
    {
        $html = $this->render([
            'blocks' => [
                ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => [
                    'Email: <b>recruitment@example.com</b>',
                    '<b>Instagram:</b> @careers_example',
                    '<b>Linktree:</b> <a target="_blank" href="https://linktr.ee/recruitmentexample">https://linktr.ee/recruitmentexample</a>',
                    '<b>Address:</b> Jl. Gajah Mada No.22',
                ]]],
            ],
        ]);

        $this->assertStringContainsString('<b>recruitment@example.com</b>', $html);
        $this->assertStringContainsString('<b>Instagram:</b>', $html);
        $this->assertStringContainsString('<b>Address:</b>', $html);
        $this->assertStringContainsString('href="https://linktr.ee/recruitmentexample"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="nofollow noopener"', $html);
    }
}