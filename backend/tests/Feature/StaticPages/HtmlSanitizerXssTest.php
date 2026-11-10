<?php

namespace Tests\Feature\StaticPages;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * XSS payloads against the allow-list sanitizer used for Static Page content
 * (stored on save in Admin\StaticPageController, re-cleaned on output).
 */
class HtmlSanitizerXssTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function xssPayloads(): array
    {
        return [
            'plain javascript href' => ['<a href="javascript:alert(1)">x</a>'],
            'mixed case scheme' => ['<a href="JaVaScRiPt:alert(1)">x</a>'],
            'decimal entity' => ['<a href="&#106;avascript:alert(1)">x</a>'],
            'decimal entity without semicolon' => ['<a href="&#106avascript:alert(1)">x</a>'],
            'zero padded entities' => ['<a href="&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058alert(1)">x</a>'],
            'hex entity and colon' => ['<a href="&#x6A;avascript&#x3A;alert(1)">x</a>'],
            'named colon entity' => ['<a href="javascript&colon;alert(1)">x</a>'],
            'double encoded entity' => ['<a href="&amp;#106;avascript:alert(1)">x</a>'],
            'tab inside scheme' => ['<a href="java&#x09;script:alert(1)">x</a>'],
            'named tab inside scheme' => ['<a href="java&Tab;script:alert(1)">x</a>'],
            'newline inside scheme' => ["<a href=\"java\nscript:alert(1)\">x</a>"],
            'carriage return inside scheme' => ['<a href="jav&#x0D;ascript:alert(1)">x</a>'],
            'leading control chars' => ['<a href=" &#14; javascript:alert(1)">x</a>'],
            'leading null' => ['<a href="&#x00;javascript:alert(1)">x</a>'],
            'vbscript' => ['<a href="vbscript:msgbox(1)">x</a>'],
            'data uri link' => ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>'],
            'data uri image' => ['<img src="data:image/svg+xml,<svg onload=alert(1)>">'],
            'javascript image src' => ['<img src="javascript:alert(1)">'],
            'event handler' => ['<img src=x onerror=alert(1)>'],
            'uppercase event handler' => ['<img src="x" ONERROR="alert(1)">'],
            'duplicate href first wins' => ['<a href="javascript:alert(1)" href="https://ok.test">x</a>'],
            'namespaced href' => ['<a xlink:href="javascript:alert(1)">x</a>'],
            'style url' => ['<p style="background:url(javascript:alert(1))">x</p>'],
            'style url on allowed property' => ['<p style="color: url(javascript:alert(1))">x</p>'],
            'style expression' => ['<p style="width: expression(alert(1))">x</p>'],
            'style expression with spaces and case' => ['<p style="width: EXPRESSION (alert(1))">x</p>'],
            'style entity encoded expression' => ['<p style="width:e&#x78;pression(alert(1))">x</p>'],
            'style css escape' => ['<p style="color: u\\72l(javascript:alert(1))">x</p>'],
            'style comment smuggling' => ['<p style="color:red/**/;behavior:url(x.htc)">x</p>'],
            'svg script' => ['<svg><script>alert(1)</script></svg>'],
            'svg onload' => ['<svg onload=alert(1)>'],
            'math mxss' => ['<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>'],
            'math href' => ['<math href="javascript:alert(1)">x</math>'],
            'nested script split' => ['<scr<script>ipt>alert(1)</scr</script>ipt>'],
            'early root close' => ['</div><script>alert(1)</script><div>'],
            'document close' => ['</div></body></html><img src=x onerror=alert(1)>'],
            'noscript title breakout' => ['<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>'],
            'xmp raw text' => ['<xmp><img src=x onerror=alert(1)></xmp>'],
            'noembed raw text' => ['<noembed><img src=x onerror=alert(1)></noembed>'],
            'select style mxss' => ['<select><style></select><img src=x onerror=alert(1)></style>'],
            'iframe srcdoc' => ['<iframe srcdoc="<script>alert(1)</script>"></iframe>'],
            'form action' => ['<form action="javascript:alert(1)"><button>x</button></form>'],
            'object data' => ['<object data="javascript:alert(1)"></object>'],
            'embed src' => ['<embed src="javascript:alert(1)">'],
            'base href' => ['<base href="javascript:alert(1)//">'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=javascript:alert(1)">'],
            'details ontoggle' => ['<details open ontoggle=alert(1)>x</details>'],
            'comment' => ['<!--<img src=x onerror=alert(1)>-->'],
            'attribute breakout via quotes' => ['<a href="x&quot; onclick=&quot;alert(1)">x</a>'],
            'numeric attribute injection' => ['<img src="https://ok.test/x.png" width="100" height="50px&quot; onerror=&quot;x">'],
        ];
    }

    #[DataProvider('xssPayloads')]
    public function test_payload_is_neutralized(string $payload): void
    {
        $clean = (string) HtmlSanitizer::clean($payload);
        $this->assertStringNotContainsStringIgnoringCase('<script', $clean);

        // Re-parse the output like a browser would and inspect the resulting DOM.
        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$clean.'</body></html>');
        libxml_clear_errors();

        foreach ((new \DOMXPath($document))->query('//body//*') as $element) {
            $tag = strtolower($element->nodeName);
            $this->assertNotContains($tag, ['script', 'style', 'svg', 'math', 'iframe', 'object', 'embed', 'form', 'base', 'meta', 'details'], "{$tag} survived: {$clean}");

            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($attribute->nodeValue, ENT_QUOTES | ENT_HTML5)));

                $this->assertStringStartsNotWith('on', $name, "event handler survived: {$clean}");
                $this->assertNotContains($name, ['srcdoc', 'action', 'data', 'xlink:href', 'formaction'], "{$name} survived: {$clean}");

                if (in_array($name, ['href', 'src'], true)) {
                    $this->assertDoesNotMatchRegularExpression('/^(javascript|vbscript|data):/', $value, "dangerous URL survived: {$clean}");
                }

                if ($name === 'style') {
                    $this->assertDoesNotMatchRegularExpression('/url\s*\(|expression\s*\(|behavior|\\\\/', $value, "dangerous style survived: {$clean}");
                }
            }
        }
    }

    public function test_safe_rich_text_is_preserved(): void
    {
        $html = '<h2 dir="rtl" style="text-align: right">مرحبا</h2>'
            .'<p style="color: #fff; font-weight: bold">Hi <a href="https://ok.test/a?x=1&amp;y=2" target="_blank">link</a>'
            .' <a href="/pages/terms">terms</a> <a href="#top">top</a> <a href="mailto:a@b.test">mail</a></p>'
            .'<ol start="3"><li>one</li></ol><table><tr><td colspan="2">c</td></tr></table>'
            .'<img src="https://ok.test/x.png" alt="x" width="100">';

        $clean = HtmlSanitizer::clean($html);

        $this->assertStringContainsString('<h2 dir="rtl" style="text-align: right">مرحبا</h2>', $clean);
        $this->assertStringContainsString('style="color: #fff; font-weight: bold"', $clean);
        $this->assertStringContainsString('href="https://ok.test/a?x=1&amp;y=2" target="_blank" rel="noopener noreferrer"', $clean);
        $this->assertStringContainsString('href="/pages/terms"', $clean);
        $this->assertStringContainsString('href="#top"', $clean);
        $this->assertStringContainsString('href="mailto:a@b.test"', $clean);
        $this->assertStringContainsString('<ol start="3">', $clean);
        $this->assertStringContainsString('<td colspan="2">c</td>', $clean);
        $this->assertStringContainsString('<img src="https://ok.test/x.png" alt="x" width="100">', $clean);
    }

    public function test_protocol_relative_and_scheme_less_sources_are_dropped(): void
    {
        $this->assertSame('<a>x</a>', HtmlSanitizer::clean('<a href="//evil.test">x</a>'));
        $this->assertSame('<img>', HtmlSanitizer::clean('<img src="#x">'));
    }

    public function test_a_stray_closing_div_does_not_drop_the_rest_of_the_content(): void
    {
        $this->assertSame('<p>a</p><p>b</p>', HtmlSanitizer::clean('<p>a</p></div><p>b</p>'));
        $this->assertSame('<div>x</div><p>c</p>', HtmlSanitizer::clean('<div>x</div></div></div><p>c</p>'));
    }

    public function test_empty_input_returns_null(): void
    {
        $this->assertNull(HtmlSanitizer::clean(null));
        $this->assertNull(HtmlSanitizer::clean('   '));
        $this->assertNull(HtmlSanitizer::clean('<script>alert(1)</script>'));
    }
}
