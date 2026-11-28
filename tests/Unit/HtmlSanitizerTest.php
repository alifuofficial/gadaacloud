<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_preserves_safe_tiptap_formatting_tables_tasklists_and_links(): void
    {
        $html = '<h2>Scope</h2><p><strong>Bold</strong> &amp; <sub>m2</sub></p>'
            . '<ul data-type="taskList"><li data-checked="true"><label><input type="checkbox" checked></label><div><p>Done</p></div></li></ul>'
            . '<table><thead><tr><th colspan="2">Item</th></tr></thead><tbody><tr><td>Row</td><td>100</td></tr></tbody></table>'
            . '<p><a href="https://gadaacloud.com">Portal</a></p>';

        $clean = HtmlSanitizer::sanitize($html);

        $this->assertNotNull($clean);
        $this->assertStringContainsString('<h2>Scope</h2>', $clean);
        $this->assertStringContainsString('<strong>Bold</strong>', $clean);
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringContainsString('colspan="2"', $clean);
        $this->assertStringContainsString('data-type="taskList"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertStringContainsString('target="_blank"', $clean);
    }

    public function test_strips_xss_scripts_event_handlers_and_javascript_urls(): void
    {
        $dirty = '<p onclick="alert(1)">Hello<script>steal()</script></p>'
            . '<a href="javascript:alert(2)">Bad</a>'
            . '<img src="javascript:alert(3)" alt="bad">'
            . '<img src="/storage/logo.png" onerror="alert(4)" alt="logo">'
            . '<iframe src="https://evil.test"></iframe>';

        $clean = HtmlSanitizer::sanitize($dirty);

        $this->assertNotNull($clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('<img src="/storage/logo.png" alt="logo">', $clean);
    }

    public function test_treats_empty_tiptap_markup_as_null_and_converts_plain_text(): void
    {
        $this->assertNull(HtmlSanitizer::sanitize('<p></p>'));
        $this->assertNull(HtmlSanitizer::sanitize('<p>&nbsp; </p>'));

        $plain = HtmlSanitizer::toPlainText('<h1>Invoice</h1><p>Line 1<br>Line 2 &amp; More</p>', 30);
        $this->assertStringStartsWith('Invoice', $plain);
        $this->assertStringContainsString('Line 1', $plain);
    }
}
