<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R4 (client retest 2026-11-24): a logged-out browser opening an API link (the old email button,
 * api.nawyweb.com/api/customer/v1/uae/orders/{number}) got HTTP 500 "Route [login] not defined"
 * with a stack trace, because the framework tried to redirect the guest to route('login').
 */
class UnauthenticatedApiResponseTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function protectedApiPaths(): array
    {
        return [
            'customer order (JWT customer guard)' => ['/api/customer/v1/uae/orders/NAWY-TEST-ORDER'],
            'customer profile' => ['/api/customer/v1/uae/profile'],
            'vendor api' => ['/api/vendor/v1/orders'],
            'partner app api' => ['/api/partner/v1/auth/me'],
            'delivery api' => ['/api/delivery/v1/assignments'],
            'marketer api' => ['/api/marketer/ad-bookings'],
        ];
    }

    #[DataProvider('protectedApiPaths')]
    public function test_a_browser_request_without_a_token_gets_json_401_not_a_500(string $path): void
    {
        // A browser: no Accept: application/json.
        $response = $this->get($path, ['Accept' => 'text/html,application/xhtml+xml']);

        $response->assertUnauthorized();
        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('Route [login]', $response->getContent());
    }

    public function test_the_customer_order_link_from_the_email_answers_401_json_for_a_guest_browser(): void
    {
        $this->get('/api/customer/v1/uae/orders/NAWY-TEST-ORDER', ['Accept' => 'text/html'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_a_json_client_still_gets_the_usual_401(): void
    {
        $this->getJson('/api/customer/v1/uae/orders/NAWY-TEST-ORDER')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function panelDashboards(): array
    {
        return [
            'admin' => ['admin.dashboard', 'admin.login'],
            'partner' => ['partner.dashboard', 'partner.login'],
            'marketer' => ['marketer.dashboard', 'marketer.login'],
            'delivery' => ['delivery.dashboard', 'delivery.login'],
            'carrier' => ['carrier.dashboard', 'carrier.login'],
            'travel agency' => ['travel-agency.dashboard', 'travel-agency.login'],
        ];
    }

    #[DataProvider('panelDashboards')]
    public function test_a_guest_browser_on_a_panel_page_is_still_sent_to_that_panel_login(string $dashboardRoute, string $loginRoute): void
    {
        $this->get(route($dashboardRoute), ['Accept' => 'text/html,application/xhtml+xml'])
            ->assertRedirect(route($loginRoute));
    }
}
