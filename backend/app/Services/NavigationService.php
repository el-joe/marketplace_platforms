<?php

namespace App\Services;

use App\Enums\ClassifiedListingStatus;
use App\Enums\DisputeStatus;
use App\Enums\ReturnRequestStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\TravelPackageStatus;
use App\Enums\VendorGlobalStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class NavigationService
{
    /**
     * Cache TTL for badge counts in seconds.
     */
    protected const BADGE_CACHE_TTL = 60;

    /**
     * Build the admin sidebar navigation, filtered by permissions.
     *
     * @return array<int, array{group: string, icon: string, items: array<int, array<string, mixed>>}>
     */
    public function adminNavigation(): array
    {
        $groups = [
            [
                'group' => __('admin.nav.overview'),
                'icon' => 'home',
                'flat' => true,
                'items' => [
                    [
                        'label' => __('admin.nav.dashboard'),
                        'route' => 'admin.dashboard',
                        'icon' => 'home',
                        'permission' => 'dashboard.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.analytics'),
                        'route' => 'admin.analytics.index',
                        'icon' => 'chart-bar',
                        'permission' => 'analytics.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.ai_dashboard'),
                        'route' => 'admin.ai.index',
                        'icon' => 'sparkles',
                        'permission' => 'dashboard.view',
                        'badge' => null,
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.catalog'),
                'icon' => 'cube',
                'items' => [
                    [
                        'label' => __('admin.nav.products'),
                        'route' => 'admin.products.index',
                        'icon' => 'cube',
                        'permission' => 'products.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.nawy_listings'),
                        'route' => 'admin.admin-listings.index',
                        'icon' => 'sparkles',
                        'permission' => 'admin_listings.view',
                        'badge' => $this->cachedBadge('out_of_stock_admin_listings', fn() => $this->countOutOfStockAdminListings(), 60),
                    ],
                    [
                        'label' => __('admin.nav.categories'),
                        'route' => 'admin.categories.index',
                        'icon' => 'rectangle-stack',
                        'permission' => 'categories.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.custom_pages'),
                        'route' => 'admin.custom-pages.index',
                        'icon' => 'document-duplicate',
                        'permission' => 'categories.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.brands'),
                        'route' => 'admin.brands.index',
                        'icon' => 'tag',
                        'permission' => 'brands.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.attributes'),
                        'route' => 'admin.attributes.index',
                        'icon' => 'adjustments-horizontal',
                        'permission' => 'attributes.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.warranty_plans'),
                        'route' => 'admin.warranty-plans.index',
                        'icon' => 'shield-check',
                        'permission' => 'warranty_plans.view',
                        'badge' => $this->cachedBadge('active_warranty_plans', fn() => $this->countActiveWarrantyPlans(), 300),
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.sales'),
                'icon' => 'shopping-cart',
                'items' => [
                    [
                        'label' => __('admin.nav.orders'),
                        'route' => 'admin.orders.index',
                        'icon' => 'shopping-cart',
                        'permission' => 'orders.view',
                        'badge' => $this->cachedBadge('pending_orders', fn() => $this->countPendingOrders()),
                    ],
                    [
                        'label' => __('admin.nav.disputes'),
                        'route' => 'admin.disputes.index',
                        'icon' => 'exclamation-triangle',
                        'permission' => 'disputes.view',
                        'badge' => $this->cachedBadge('open_disputes', fn() => $this->countOpenDisputes()),
                    ],
                    [
                        'label' => __('admin.nav.returns'),
                        'route' => 'admin.returns.index',
                        'icon' => 'arrow-uturn-left',
                        'permission' => 'returns.view',
                        'badge' => $this->cachedBadge('pending_returns', fn() => $this->countPendingReturns()),
                    ],
                    [
                        'label' => __('admin.nav.warranty_claims'),
                        'route' => 'admin.warranty-claims.index',
                        'icon' => 'shield-check',
                        'permission' => 'warranty_claims.view',
                        'badge' => $this->cachedBadge('unresolved_warranty_claims', fn() => $this->countUnresolvedWarrantyClaims()),
                    ],
                    [
                        'label' => __('admin.nav.coupons'),
                        'route' => 'admin.coupons.index',
                        'icon' => 'ticket',
                        'permission' => 'coupons.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.vouchers'),
                        'route' => 'admin.vouchers.index',
                        'icon' => 'credit-card',
                        'permission' => 'vouchers.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.flash_sales'),
                        'route' => 'admin.flash-sales.index',
                        'icon' => 'bolt',
                        'permission' => 'flash_sales.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.gift_cards'),
                        'route' => 'admin.gift-cards.index',
                        'icon' => 'gift',
                        'permission' => 'gift_cards.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.support_tickets'),
                        'route' => 'admin.support-tickets.index',
                        'icon' => 'chat-bubble-left-right',
                        'permission' => 'support.view',
                        'badge' => $this->cachedBadge('open_tickets', fn() => $this->countOpenTickets()),
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.marketing'),
                'icon' => 'megaphone',
                'items' => [
                    [
                        'label' => __('admin.nav.banners'),
                        'route' => 'admin.banners.index',
                        'icon' => 'photo',
                        'permission' => 'banners.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.cart_card_offers'),
                        'route' => 'admin.cart-card-offers.index',
                        'icon' => 'credit-card',
                        'permission' => 'cart_card_offers.view',
                        'badge' => $this->cachedBadge('active_cart_card_offers', fn() => $this->countActiveCartCardOffers()),
                    ],
                    [
                        'label'      => __('admin.nav.live_streams'),
                        'route'      => 'admin.live-streams.index',
                        'icon'       => 'video-camera',
                        'permission' => 'pages.view',
                        'badge'      => null,
                    ],
                    [
                        'label' => __('admin.nav.ad_campaigns'),
                        'route' => 'admin.ad-campaigns.index',
                        'icon' => 'megaphone',
                        'permission' => 'ad_campaigns.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.marketers'),
                        'route' => 'admin.marketers.index',
                        'icon' => 'user-group',
                        'permission' => 'marketers.view',
                        'badge' => $this->cachedBadge('pending_marketers', fn() => $this->countPendingMarketers()),
                    ],
                    [
                        'label' => __('admin.nav.marketer_campaigns'),
                        'route' => 'admin.marketer-campaigns.index',
                        'icon' => 'user-group',
                        'permission' => 'marketer_campaigns.view',
                        'badge' => $this->cachedBadge('pending_marketer_campaigns', fn() => $this->countPendingMarketerCampaigns()),
                    ],
                    [
                        'label' => __('admin.nav.marketer_campaigns_financials'),
                        'route' => 'admin.marketer-campaigns.financials',
                        'icon' => 'banknotes',
                        'permission' => 'marketer_campaigns.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.marketer_settings'),
                        'route' => 'admin.marketer-settings.index',
                        'icon' => 'cog-6-tooth',
                        'permission' => 'marketer_commission_settings.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.ad_slots'),
                        'route' => 'admin.ad-slots.index',
                        'icon' => 'rectangle-stack',
                        'permission' => 'ad_campaigns.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.paid_ad_bookings'),
                        'route' => 'admin.paid-ad-bookings.index',
                        'icon' => 'ticket',
                        'permission' => 'ad_campaigns.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.newsletter'),
                        'route' => 'admin.newsletter.index',
                        'icon' => 'envelope',
                        'permission' => 'settings.view',
                        'badge' => null,
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.users'),
                'icon' => 'users',
                'items' => [
                    [
                        'label' => __('admin.nav.customers'),
                        'route' => 'admin.customers.index',
                        'icon' => 'user-group',
                        'permission' => 'customers.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.wishlists'),
                        'route' => 'admin.wishlist.index',
                        'icon' => 'heart',
                        'permission' => 'wishlists.view',
                        'badge' => $this->cachedBadge('public_wishlist_groups', fn() => $this->countPublicWishlistGroups()),
                    ],
                    [
                        'label' => __('admin.nav.notifications'),
                        'route' => 'admin.notification-management.index',
                        'icon' => 'bell',
                        'permission' => 'notifications.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.vendors'),
                        'route' => 'admin.vendors.index',
                        'icon' => 'building-storefront',
                        'permission' => 'vendors.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.vendor_listings'),
                        'route' => 'admin.vendor-listings.index',
                        'icon' => 'list-bullet',
                        'permission' => 'vendors.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.applications'),
                        'route' => 'admin.vendor-applications.index',
                        'icon' => 'inbox-arrow-down',
                        'permission' => 'vendors.view',
                        'badge' => $this->cachedBadge('pending_vendors', fn() => $this->countPendingVendors()),
                    ],
                    [
                        'label' => __('admin.nav.vendor_change_requests'),
                        'route' => 'admin.vendor-change-requests.index',
                        'icon' => 'lock-closed',
                        'permission' => 'vendor_change_requests.view',
                        'badge' => $this->cachedBadge('pending_vendor_change_requests', fn() => $this->countPendingVendorChangeRequests()),
                    ],
                    [
                        'label' => __('admin.nav.product_certifications'),
                        'route' => 'admin.vendor-product-certifications.index',
                        'icon' => 'document-check',
                        'permission' => 'vendor_product_certifications.view',
                        'badge' => $this->cachedBadge('pending_product_certifications', fn() => $this->countPendingProductCertifications()),
                    ],
                    [
                        'label' => __('admin.nav.acquisition_commissions'),
                        'route' => 'admin.acquisition-commissions.index',
                        'icon' => 'user-plus',
                        'permission' => 'vendors.view',
                        'badge' => null,
                    ],
                    ...($this->hasActiveAcquisitionCommissions() ? [
                        [
                            'label' => __('admin.nav.my_acquisition_commissions'),
                            'route' => 'admin.my-acquisition-commissions.index',
                            'icon' => 'banknotes',
                            'badge' => null,
                        ]
                    ] : []),
                    [
                        'label' => __('admin.nav.admins'),
                        'route' => 'admin.admins.index',
                        'icon' => 'shield-check',
                        'permission' => 'admins.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.roles'),
                        'route' => 'admin.roles.index',
                        'icon' => 'key',
                        'permission' => 'roles.view',
                        'badge' => null,
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.classifieds'),
                'icon' => 'home-modern',
                'items' => [
                    [
                        'label' => __('admin.nav.categories'),
                        'route' => 'admin.classifieds.categories.index',
                        'icon' => 'rectangle-stack',
                        'permission' => 'classifieds.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.contract_templates'),
                        'route' => 'admin.classifieds.contract-templates.index',
                        'icon' => 'document-text',
                        'permission' => 'classifieds.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.listings'),
                        'route' => 'admin.classifieds.listings.index',
                        'icon' => 'list-bullet',
                        'permission' => 'classifieds.view',
                        'badge' => $this->cachedBadge('pending_classifieds', fn() => $this->countPendingClassifieds()),
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.travel'),
                'icon' => 'globe-alt',
                'items' => [
                    [
                        'label' => __('admin.nav.agencies'),
                        'route' => 'admin.travel.agencies.index',
                        'icon' => 'building-office',
                        'permission' => 'travel.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.packages'),
                        'route' => 'admin.travel.packages.index',
                        'icon' => 'briefcase',
                        'permission' => 'travel.view',
                        'badge' => $this->cachedBadge('pending_travel_packages', fn() => $this->countPendingTravelPackages()),
                    ],
                    [
                        'label' => __('admin.nav.bookings'),
                        'route' => 'admin.travel.bookings.index',
                        'icon' => 'ticket',
                        'permission' => 'travel.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.countries'),
                        'route' => 'admin.travel.countries.index',
                        'icon' => 'flag',
                        'permission' => 'travel.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.cities'),
                        'route' => 'admin.travel.cities.index',
                        'icon' => 'map-pin',
                        'permission' => 'travel.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.inclusions'),
                        'route' => 'admin.travel.inclusions.index',
                        'icon' => 'check-badge',
                        'permission' => 'travel.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.travel_categories'),
                        'route' => 'admin.travel.categories.index',
                        'icon' => 'tag',
                        'permission' => 'travel.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.travel_inquiries'),
                        'route' => 'admin.travel.inquiries.index',
                        'icon' => 'chat-bubble-left-right',
                        'permission' => 'travel.view',
                        'badge' => null,
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.delivery'),
                'icon' => 'truck',
                'items' => [
                    [
                        'label' => __('admin.nav.delivery_agents'),
                        'route' => 'admin.delivery.agents.index',
                        'icon' => 'user-group',
                        'permission' => 'payouts.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.delivery_zones'),
                        'route' => 'admin.delivery.zones.index',
                        'icon' => 'map-pin',
                        'permission' => 'payouts.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.delivery_assignments'),
                        'route' => 'admin.delivery.assignments.index',
                        'icon' => 'arrows-right-left',
                        'permission' => 'payouts.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.delivery_payouts'),
                        'route' => 'admin.delivery.payouts.index',
                        'icon' => 'banknotes',
                        'permission' => 'payouts.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.carrier_claims'),
                        'route' => 'admin.carrier-claims.index',
                        'icon' => 'exclamation-triangle',
                        'permission' => 'payouts.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.carrier_scorecard'),
                        'route' => 'admin.carrier-scorecard.index',
                        'icon' => 'chart-bar',
                        'permission' => 'payouts.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.shipping_companies'),
                        'route' => 'admin.shipping-companies.index',
                        'icon' => 'building-office',
                        'permission' => 'settings.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.packaging_catalog'),
                        'route' => 'admin.packaging.catalog',
                        'icon' => 'cube',
                        'permission' => 'packaging.manage',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.packaging_orders'),
                        'route' => 'admin.packaging.requests',
                        'icon' => 'clipboard-document-list',
                        'permission' => 'packaging.manage',
                        'badge' => $this->cachedBadge('packaging_pending_count', fn() => $this->countPendingPackagingRequests(), 60),
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.finance'),
                'icon' => 'banknotes',
                'items' => [
                    [
                        'label' => __('admin.nav.payouts'),
                        'route' => 'admin.payouts.index',
                        'icon' => 'banknotes',
                        'permission' => 'payouts.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.transactions'),
                        'route' => 'admin.transactions.index',
                        'icon' => 'arrow-trending-up',
                        'permission' => 'transactions.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.ledger'),
                        'route' => 'admin.ledger.index',
                        'icon' => 'book-open',
                        'permission' => 'ledger.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.cod_settlements'),
                        'route' => 'admin.delivery.cod-settlements.index',
                        'icon' => 'banknotes',
                        'permission' => 'payouts.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.financial_reports'),
                        'route' => 'admin.reports.financial.index',
                        'icon' => 'chart-bar-square',
                        'permission' => 'analytics.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.warranty_purchases'),
                        'route' => 'admin.warranty-purchases.index',
                        'icon' => 'shield-check',
                        'permission' => 'warranty_plans.view',
                        'badge' => $this->cachedBadge('pending_warranty_purchases', fn() => $this->countPendingWarrantyPurchases(), 300),
                    ],
                    [
                        'label' => __('admin.nav.wallets'),
                        'route' => 'admin.wallets.index',
                        'icon' => 'banknotes',
                        'permission' => 'payouts.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.subscriptions'),
                        'route' => 'admin.subscriptions.index',
                        'icon' => 'banknotes',
                        'permission' => 'transactions.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.subscription_plans'),
                        'route' => 'admin.subscriptions.plans.index',
                        'icon' => 'rectangle-stack',
                        'permission' => 'transactions.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.subscription_invoices'),
                        'route' => 'admin.subscriptions.invoices.index',
                        'icon' => 'document-text',
                        'permission' => 'transactions.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.fbn_inbound'),
                        'route' => 'admin.fbn.inbound.index',
                        'icon' => 'inbox-stack',
                        'permission' => 'warehouses.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.fbn_marketplace'),
                        'route' => 'admin.fbn.marketplace.index',
                        'icon' => 'inbox-stack',
                        'permission' => 'warehouses.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.fbn_storage_fees'),
                        'route' => 'admin.fbn.storage-fees.index',
                        'icon' => 'inbox-stack',
                        'permission' => 'warehouses.view',
                        'badge' => null,
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.content'),
                'icon' => 'document-text',
                'items' => [
                    [
                        'label' => __('admin.nav.pages'),
                        'route' => 'admin.page-builder.index',
                        'icon' => 'document-text',
                        'permission' => 'pages.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.app_contexts'),
                        'route' => 'admin.app-contexts.index',
                        'icon' => 'squares-2x2',
                        'permission' => 'app_contexts.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.reviews'),
                        'route' => 'admin.reviews.index',
                        'icon' => 'star',
                        'permission' => 'reviews.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.blog_categories'),
                        'route' => 'admin.blog.categories.index',
                        'icon' => 'rectangle-stack',
                        'permission' => 'pages.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.blog'),
                        'route' => 'admin.blog.posts.index',
                        'icon' => 'pencil-square',
                        'permission' => 'pages.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.adsupport_collections'),
                        'route' => 'admin.adsupport.collections.index',
                        'icon' => 'rectangle-stack',
                        'permission' => 'pages.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.adsupport_articles'),
                        'route' => 'admin.adsupport.articles.index',
                        'icon' => 'information-circle',
                        'permission' => 'pages.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.faqs'),
                        'route' => 'admin.faqs.index',
                        'icon' => 'information-circle',
                        'permission' => 'faqs.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.portal_content'),
                        'route' => 'admin.portal-content.index',
                        'icon' => 'document-text',
                        'permission' => 'portal_content.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.content_settings'),
                        'route' => 'admin.content-settings.index',
                        'icon' => 'cog',
                        'permission' => 'settings.content',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.radio_channels'),
                        'route' => 'admin.radio.channels.index',
                        'icon' => 'play-circle',
                        'permission' => 'pages.view',
                        'badge' => null,
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.helpcenter_group'),
                'icon' => 'information-circle',
                'items' => [
                    [
                        'label' => __('admin.nav.helpcenter_categories'),
                        'route' => 'admin.helpcenter.categories.index',
                        'icon' => 'rectangle-stack',
                        'permission' => 'pages.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.helpcenter_articles'),
                        'route' => 'admin.helpcenter.articles.index',
                        'icon' => 'information-circle',
                        'permission' => 'pages.view',
                        'badge' => null,
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.system'),
                'icon' => 'cog-6-tooth',
                'items' => [
                    [
                        'label' => __('admin.nav.countries'),
                        'route' => 'admin.countries.index',
                        'icon' => 'globe-alt',
                        'permission' => 'countries.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.cities'),
                        'route' => 'admin.cities.index',
                        'icon' => 'map-pin',
                        'permission' => 'countries.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.currencies'),
                        'route' => 'admin.currencies.index',
                        'icon' => 'currency-dollar',
                        'permission' => 'countries.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.shipping_zones'),
                        'route' => 'admin.shipping-zones.index',
                        'icon' => 'truck',
                        'permission' => 'countries.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.shipping_methods'),
                        'route' => 'admin.shipping-methods.index',
                        'icon' => 'cube',
                        'permission' => 'settings.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.shipping_settings'),
                        'route' => 'admin.shipping-settings.index',
                        'icon' => 'cog-6-tooth',
                        'permission' => 'settings.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.document_types'),
                        'route' => 'admin.vendor-document-types.index',
                        'icon' => 'document-check',
                        'permission' => 'settings.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.payment_gateways'),
                        'route' => 'admin.payment-gateways.index',
                        'icon' => 'credit-card',
                        'permission' => 'settings.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.shipping_weight_slabs'),
                        'route' => 'admin.shipping.weight-slabs.index',
                        'icon' => 'truck',
                        'permission' => 'settings.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.shipping_subsidies'),
                        'route' => 'admin.shipping-subsidies.index',
                        'icon' => 'truck',
                        'permission' => 'settings.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.vendor_zone_alerts'),
                        'route' => 'admin.shipping-subsidies.alerts.index',
                        'icon' => 'exclamation-triangle',
                        'permission' => 'settings.view',
                        'badge' => $this->cachedBadge('pending_zone_alerts', fn() => \App\Models\VendorExceptionalZoneAlert::where('status', 'pending')->count()),
                    ],
                    [
                        'label' => __('admin.nav.warehouses'),
                        'route' => 'admin.warehouses.index',
                        'icon' => 'building-office-2',
                        'permission' => 'warehouses.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.inventory_transfers'),
                        'route' => 'admin.warehouses.transfers.index',
                        'icon' => 'arrows-right-left',
                        'permission' => 'warehouses.view',
                        'badge' => null,
                    ],
                    [
                        'label' => __('admin.nav.warehouse_shipping_surcharges'),
                        'route' => 'admin.warehouses.shipping-surcharges.index',
                        'icon' => 'currency-dollar',
                        'permission' => 'warehouses.view',
                        'badge' => null,
                    ],
                    // [
                    //     'label' => __('admin.nav.settings'),
                    //     'route' => 'admin.settings.index',
                    //     'icon' => 'cog-6-tooth',
                    //     'permission' => 'settings.view',
                    //     'badge' => null,
                    // ],
                    [
                        'label' => __('admin.nav.activity_log'),
                        'route' => 'admin.activity-log.index',
                        'icon' => 'clipboard-document-list',
                        'permission' => 'activity-log.view',
                        'badge' => null,
                    ],
                ],
            ],
            [
                'group' => __('admin.nav.documentation'),
                'icon' => 'book-open',
                'items' => [
                    ['label' => __('admin.nav.docs_overview'), 'route' => 'admin.docs.index', 'icon' => 'book-open', 'permission' => null, 'badge' => null],

                    ['label' => __('admin.nav.docs_panel_admin'), 'route' => 'admin.docs.panels.admin', 'icon' => 'shield-check', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_panel_partner'), 'route' => 'admin.docs.panels.partner', 'icon' => 'building-storefront', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_panel_travel'), 'route' => 'admin.docs.panels.travel', 'icon' => 'globe-alt', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_panel_delivery'), 'route' => 'admin.docs.panels.delivery', 'icon' => 'truck', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_panel_carrier'), 'route' => 'admin.docs.panels.carrier', 'icon' => 'truck', 'permission' => null, 'badge' => null],

                    ['label' => __('admin.nav.docs_order_lifecycle'), 'route' => 'admin.docs.features.order-lifecycle', 'icon' => 'shopping-cart', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_shipping'), 'route' => 'admin.docs.features.shipping', 'icon' => 'truck', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_warehouses'), 'route' => 'admin.docs.features.warehouses', 'icon' => 'building-office-2', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_payments'), 'route' => 'admin.docs.features.payments', 'icon' => 'credit-card', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_finance'), 'route' => 'admin.docs.features.finance', 'icon' => 'banknotes', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_subsidy'), 'route' => 'admin.docs.features.subsidy', 'icon' => 'truck', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_page_builder'), 'route' => 'admin.docs.features.page-builder', 'icon' => 'document-text', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_banners'), 'route' => 'admin.docs.features.banners', 'icon' => 'photo', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_ad_campaigns'), 'route' => 'admin.docs.features.ad-campaigns', 'icon' => 'megaphone', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_vendor_campaigns'), 'route' => 'admin.docs.features.vendor-campaigns', 'icon' => 'rectangle-group', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_flash_sales'), 'route' => 'admin.docs.features.flash-sales', 'icon' => 'bolt', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_packaging'), 'route' => 'admin.docs.features.packaging', 'icon' => 'cube', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_warranties'), 'route' => 'admin.docs.features.warranties', 'icon' => 'shield-check', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_classifieds'), 'route' => 'admin.docs.features.classifieds', 'icon' => 'home-modern', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_travel'), 'route' => 'admin.docs.features.travel', 'icon' => 'briefcase', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_radio'), 'route' => 'admin.docs.features.radio', 'icon' => 'play-circle', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_roles'), 'route' => 'admin.docs.features.roles', 'icon' => 'key', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_content_pages'), 'route' => 'admin.docs.features.content-pages', 'icon' => 'document-text', 'permission' => null, 'badge' => null],
                    ['label' => __('admin.nav.docs_system_pages'), 'route' => 'admin.docs.features.system-pages', 'icon' => 'cog-6-tooth', 'permission' => null, 'badge' => null],
                ],
            ],
        ];

        return $this->filterByPermissions($groups);
    }

    /**
     * Filter navigation groups + items by user permissions.
     */
    protected function filterByPermissions(array $groups): array
    {
        $user = Auth::guard('admin')->user();

        $filtered = [];
        foreach ($groups as $group) {
            $items = array_values(array_filter($group['items'], function ($item) use ($user) {
                if (empty($item['permission'])) {
                    return true;
                }
                if (!$user) {
                    return false;
                }
                return method_exists($user, 'can') ? $user->can($item['permission']) : false;
            }));

            if (!empty($items)) {
                $group['items'] = $items;
                $filtered[] = $group;
            }
        }

        return $filtered;
    }

    /**
     * Detect if the current route matches a route pattern.
     */
    public static function isActive(string $routeName): bool
    {
        // Treat "admin.products.index" as a leaf and "admin.products.*" as a section.
        if (str_ends_with($routeName, '.*')) {
            return request()->routeIs($routeName);
        }
        return request()->routeIs($routeName) || request()->routeIs(rtrim($routeName, '.index') . '.*');
    }

    /**
     * Detect if any item in a group is active.
     */
    public static function isGroupActive(array $group): bool
    {
        foreach ($group['items'] as $item) {
            if (self::isActive($item['route'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cache a badge count value for 60 seconds.
     */
    protected function cachedBadge(string $key, \Closure $resolver, ?int $ttl = null): ?int
    {
        $count = Cache::remember("nav.badge.{$key}", $ttl ?? self::BADGE_CACHE_TTL, $resolver);
        return $count > 0 ? (int) $count : null;
    }

    protected function countOutOfStockAdminListings(): int
    {
        try {
            return (int) \App\Models\AdminListing::query()
                ->where('status', \App\Enums\AdminListingStatus::OutOfStock)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countActiveWarrantyPlans(): int
    {
        if (!class_exists(\App\Models\WarrantyPlan::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\WarrantyPlan::query()->where('is_active', true)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countPendingWarrantyPurchases(): int
    {
        if (!class_exists(\App\Models\WarrantyPurchase::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\WarrantyPurchase::query()->where('status', 'pending')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countPendingPackagingRequests(): int
    {
        if (!class_exists(\App\Models\PackagingSupplyRequest::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\PackagingSupplyRequest::query()->where('status', 'pending')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countPendingOrders(): int
    {
        if (!class_exists(\App\Models\Order::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\Order::query()->where('status', 'pending')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countOpenDisputes(): int
    {
        if (!class_exists(\App\Models\Dispute::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\Dispute::query()->where('status', DisputeStatus::Open->value)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countPendingReturns(): int
    {
        if (!class_exists(\App\Models\ReturnRequest::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\ReturnRequest::query()
                ->where('status', ReturnRequestStatus::Requested->value)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countUnresolvedWarrantyClaims(): int
    {
        if (!class_exists(\App\Models\WarrantyClaim::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\WarrantyClaim::query()
                ->whereNotIn('status', [\App\Models\WarrantyClaim::STATUS_RESOLVED, \App\Models\WarrantyClaim::STATUS_REJECTED])
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function hasActiveAcquisitionCommissions(): bool
    {
        $adminId = Auth::guard('admin')->id();

        if (!$adminId) {
            return false;
        }

        return \App\Models\VendorAcquisitionCommission::where('admin_id', $adminId)
            ->where('status', 'active')
            ->exists();
    }

    protected function countPublicWishlistGroups(): int
    {
        if (!class_exists(\App\Models\WishlistGroup::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\WishlistGroup::query()->where('is_public', true)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countPendingVendors(): int
    {
        if (!class_exists(\App\Models\Vendor::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\Vendor::query()->where('global_status', VendorGlobalStatus::Pending->value)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countPendingProductCertifications(): int
    {
        if (!class_exists(\App\Models\VendorProductCertification::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\VendorProductCertification::query()->where('status', 'pending')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countPendingVendorChangeRequests(): int
    {
        if (!class_exists(\App\Models\VendorChangeRequest::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\VendorChangeRequest::query()->where('status', 'pending')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countOpenTickets(): int
    {
        try {
            return (int) \App\Models\SupportTicket::query()->where('status', SupportTicketStatus::Open->value)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countPendingMarketerCampaigns(): int
    {
        try {
            return (int) \App\Models\MarketerCampaign::query()->where('status', 'pending_admin')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countPendingMarketers(): int
    {
        try {
            return (int) \App\Models\Marketer::query()->where('global_status', 'pending')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countActiveCartCardOffers(): int
    {
        if (!class_exists(\App\Models\CartCardOffer::class)) {
            return 0;
        }
        try {
            return (int) \App\Models\CartCardOffer::query()->active()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countPendingClassifieds(): int
    {
        try {
            return (int) \App\Models\ClassifiedListing::query()->where('status', ClassifiedListingStatus::PendingReview->value)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function hasInstallmentPaymentMethods(): bool
    {
        // BNPL installment badge removed — country_payment_methods table no longer exists.
        // Re-enable when BNPL gateways are added to the payment_gateways table.
        return false;
    }

    protected function countPendingTravelPackages(): int
    {
        try {
            return (int) \App\Models\TravelPackage::query()->where('status', TravelPackageStatus::PendingReview->value)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
