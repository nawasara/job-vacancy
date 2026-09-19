<?php

namespace Nawasara\JobVacancy\Tests\Feature;

use Nawasara\JobVacancy\Support\EditorJsRenderer;
use Tests\TestCase;

class EditorJsRendererSanitizerTest extends TestCase
{
    public function test_url_allows_https_relative_and_mailto(): void
    {
        $this->assertSame('https://linktr.ee/x', EditorJsRenderer::sanitizeUrl('https://linktr.ee/x'));
        $this->assertSame('/download/b.pdf', EditorJsRenderer::sanitizeUrl('/download/b.pdf'));
        $this->assertSame('mailto:recruitment@example.com', EditorJsRenderer::sanitizeUrl('mailto:recruitment@example.com'));
    }

    public function test_url_blocks_unsafe_schemes_and_control_chars(): void
    {
        $this->assertSame('#', EditorJsRenderer::sanitizeUrl('javascript:alert(1)'));
        $this->assertSame('#', EditorJsRenderer::sanitizeUrl('vbscript:alert(1)'));
        $this->assertSame('#', EditorJsRenderer::sanitizeUrl('data:text/html,<script>'));
        $this->assertSame('#', EditorJsRenderer::sanitizeUrl('x" onmouseover="alert(1)'));
    }

    public function test_keeps_formatting_and_links_but_strips_unsafe_attributes(): void
    {
        $html = EditorJsRenderer::sanitizeHtml(
            '<b>Linktree:</b> <a target="_blank" href="https://linktr.ee/x" onclick="evil()">https://linktr.ee/x</a>'
        );

        $this->assertStringContainsString('<b>Linktree:</b>', $html);
        $this->assertStringContainsString('href="https://linktr.ee/x"', $html);
        $this->assertStringContainsString('rel="nofollow noopener"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_removes_scripts_entirely_and_unwraps_structural_tags(): void
    {
        $html = EditorJsRenderer::sanitizeHtml(
            '<div id="x"><script>alert(1)</script><font color="red">A B</font></div>'
        );

        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('<div', $html);
        $this->assertStringNotContainsString('<font', $html);
        $this->assertStringContainsString('A B', $html);
    }

    public function test_strips_javascript_href_and_event_handlers(): void
    {
        $html = EditorJsRenderer::sanitizeHtml(
            '<a href="javascript:alert(1)">Evil</a> <a href="/ok" onmouseover="x()">Safe</a>'
        );

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onmouseover', $html);
        $this->assertStringContainsString('href="/ok"', $html);
        $this->assertStringContainsString('Evil', $html);
    }

    public function test_preserves_utf8_text(): void
    {
        $html = EditorJsRenderer::sanitizeHtml('Qualifications é Indonesia 💼');
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString('Qualifications', $html);
        $this->assertStringContainsString('é', $decoded);
        $this->assertStringContainsString('Indonesia', $html);
        $this->assertStringContainsString('💼', $decoded);
    }

    public function test_falls_back_safely_when_html_unparsable(): void
    {
        $html = EditorJsRenderer::sanitizeHtml('<div  <script');

        $this->assertStringNotContainsString('<script', $html);
    }
}