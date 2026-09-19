<?php
namespace Tests\Feature;
use App\Http\Controllers\Admin\CurrencyController;
use Tests\TestCase;
class CurrencySymbolSvgTest extends TestCase
{
    public function test_svg_sanitised(): void
    {
        $out = CurrencyController::sanitizeSvg('<svg onload="x()"><script>alert(1)</script><a href="http://e.com"/><path d="M0"/></svg>');
        $this->assertStringNotContainsString('script', $out);
        $this->assertStringNotContainsString('onload', $out);
        $this->assertStringNotContainsString('http://e.com', $out);
        $this->assertStringContainsString('<path', $out);
        $this->assertNull(CurrencyController::sanitizeSvg('<html></html>'));
    }
}
