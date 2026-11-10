<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\CMS;

use App\Domains\CMS\Services\HtmlSanitizerService;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerServiceTest extends TestCase
{
    private HtmlSanitizerService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizerService;
    }

    public function test_it_preserves_safe_editorial_markup(): void
    {
        $input = '<h1>Title</h1><p>Paragraph with <strong>bold</strong> and <em>italic</em>.</p><blockquote>Quote</blockquote>';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('<h1>Title</h1>', $output);
        $this->assertStringContainsString('<strong>bold</strong>', $output);
        $this->assertStringContainsString('<em>italic</em>', $output);
        $this->assertStringContainsString('<blockquote>Quote</blockquote>', $output);
    }

    public function test_it_preserves_safe_inline_colors_and_text_alignment(): void
    {
        $input = '<p style="color: rgb(224, 62, 45); text-align: center;">Centered red text</p>';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('style="color: rgb(224, 62, 45); text-align: center"', $output);
        $this->assertStringContainsString('Centered red text', $output);
    }

    public function test_it_preserves_table_structure_and_attributes(): void
    {
        $input = '<table border="1" style="width: 100%; border-collapse: collapse;"><thead><tr><th scope="col" colspan="2">Header</th></tr></thead><tbody><tr><td>Cell 1</td><td>Cell 2</td></tr></tbody></table>';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('<table', $output);
        $this->assertStringContainsString('border="1"', $output);
        $this->assertStringContainsString('colspan="2"', $output);
        $this->assertStringContainsString('Cell 1', $output);
    }

    public function test_it_removes_dangerous_elements_and_their_payloads(): void
    {
        $input = '<p>Safe</p><script>alert("xss")</script><iframe src="https://evil.com"></iframe><style>body{display:none}</style>';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('script', $output);
        $this->assertStringNotContainsString('alert', $output);
        $this->assertStringNotContainsString('iframe', $output);
        $this->assertStringNotContainsString('style>', $output);
        $this->assertEquals('<p>Safe</p>', $output);
    }

    public function test_it_strips_inline_event_handlers(): void
    {
        $input = '<img src="https://example.com/pic.jpg" alt="Photo" onerror="alert(1)" onclick="evil()"><p onmouseover="bad()">Text</p>';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onerror', $output);
        $this->assertStringNotContainsString('onclick', $output);
        $this->assertStringNotContainsString('onmouseover', $output);
        $this->assertStringContainsString('src="https://example.com/pic.jpg"', $output);
    }

    public function test_it_strips_javascript_urls(): void
    {
        $input = '<a href="javascript:alert(1)">Click</a><img src="javascript:alert(2)" alt="X">';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('javascript:', $output);
    }

    public function test_it_rejects_malicious_css_in_style_attributes(): void
    {
        $inputs = [
            '<p style="background: url(javascript:alert(1));">Text 1</p>',
            '<p style="behavior: url(xss.htc);">Text 2</p>',
            '<p style="width: expression(alert(1));">Text 3</p>',
            '<p style="color: red; -moz-binding: url(xss.xml);">Text 4</p>',
            '<p style="@import \'https://evil.com/x.css\'; color: blue;">Text 5</p>',
        ];

        foreach ($inputs as $input) {
            $output = $this->sanitizer->sanitize($input);
            $this->assertStringNotContainsString('javascript', $output);
            $this->assertStringNotContainsString('behavior', $output);
            $this->assertStringNotContainsString('expression', $output);
            $this->assertStringNotContainsString('-moz-binding', $output);
            $this->assertStringNotContainsString('@import', $output);
        }
    }
}
