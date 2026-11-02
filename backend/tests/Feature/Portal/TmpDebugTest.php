<?php
namespace Tests\Feature\Portal;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class TmpDebugTest extends TestCase
{
    use RefreshDatabase;
    public function test_x(): void
    {
        Country::factory()->create(['site_code'=>'kwt','iso_code_2'=>'KW','is_active'=>true,'is_launched'=>true]);
        $r = $this->withHeader('CF-IPCountry','KW')->get(route('portal.faq'));
        fwrite(STDERR, "IP=".request()->ip()." H=".request()->header('CF-IPCountry')." S=".json_encode(session()->all())." det=".json_encode(Country::resolveFromIp('127.0.0.1'))."\n");
        $this->assertTrue(true);
    }
}
