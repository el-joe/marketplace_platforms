<?php

namespace App\Providers;

use App\Http\View\Composers\SettingsComposer;
use App\Http\View\Composers\TravelAgencySidebarComposer;
use App\View\Components\Form\AsyncSelect;
use App\View\Components\Form\FileUpload;
use App\View\Components\Form\Input;
use App\View\Components\Form\PriceInput;
use App\View\Components\Form\RichEditor;
use App\View\Components\Form\Select;
use App\Events\SubOrderPlaced;
use App\Listeners\InvalidateVendorDashboardCache;
use App\Services\GiftCardService;
use App\Services\AppContextService;
use App\Services\Shared\PageBuilderService;
use App\Services\Customer\CheckoutCalculationService;
use App\Services\Customer\ListingIdentifierService;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\UnifiedCategoryService;
use App\Services\Shipping\ShippingCarrierFactory;
use App\Services\Vendor\VendorFCMService;
use App\Services\Carrier\CarrierFCMService;
use App\Services\Delivery\DeliveryFCMService;
use App\Notifications\Channels\VendorPushChannel;
use App\Models\Address;
use App\Models\Country;
use App\Models\FlashSaleSubmission;
use App\Models\MarketerCampaign;
use App\Models\Payout;
use App\Models\SubOrder;
use App\Models\VendorBankAccount;
use App\Models\ReturnRequest;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAgent;
use App\Models\ShippingCompanySupervisor;
use App\Policies\AddressPolicy;
use App\Policies\CarrierAgentPolicy;
use App\Policies\SupervisorPolicy;
use App\Policies\DeliveryAssignmentPolicy;
use App\Policies\FlashSaleSubmissionPolicy;
use App\Policies\PayoutPolicy;
use App\Policies\SubOrderPolicy;
use App\Policies\VendorBankAccountPolicy;
use App\Policies\ReturnRequestPolicy;
use App\Policies\VendorListingPolicy;
use App\Policies\WarehouseInventoryPolicy;
use App\Models\ClassifiedListing;
use App\Models\ClassifiedInquiry;
use App\Models\PaidAdSlot;
use App\Policies\AdSlotPolicy;
use App\Policies\ClassifiedListingPolicy;
use App\Policies\ClassifiedInquiryPolicy;
use App\Observers\SubOrderObserver;
use App\Observers\VendorListingObserver;
use App\Observers\WarrantyPurchaseObserver;
use App\Models\WarrantyPurchase;
use App\Models\AdminListing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Observers\AdminListingObserver;
use App\Observers\CouponObserver;
use App\Models\Coupon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Auth\TravelAgencyUserProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShippingCarrierFactory::class);
        $this->app->singleton(PageBuilderService::class);
        $this->app->singleton(ListingQueryService::class);
        $this->app->singleton(ListingIdentifierService::class);
        $this->app->singleton(UnifiedCategoryService::class);
        $this->app->singleton(CheckoutCalculationService::class);
        $this->app->singleton(VendorFCMService::class);
        $this->app->singleton(CarrierFCMService::class);
        $this->app->singleton(DeliveryFCMService::class);
        $this->app->singleton(GiftCardService::class);
        $this->app->singleton(AppContextService::class, fn () => new AppContextService());
        $this->app->singleton(\App\Services\Customer\LoyaltyService::class);

        // Replace Laravel's built-in DatabaseChannel with our custom one that
        // writes to the platform's non-standard notifications table schema
        // (has required `channel` enum column, no `updated_at` column).
        $this->app->bind(
            \Illuminate\Notifications\Channels\DatabaseChannel::class,
            \App\Notifications\Channels\CustomDatabaseChannel::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('travel_agency_provider', function ($app, array $config) {
            return new TravelAgencyUserProvider($app['hash'], $config['model']);
        });

        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return true;
            }
            return null;
        });

        Gate::policy(Address::class, AddressPolicy::class);
        Gate::policy(FlashSaleSubmission::class, FlashSaleSubmissionPolicy::class);
        Gate::policy(Payout::class, PayoutPolicy::class);
        Gate::policy(SubOrder::class, SubOrderPolicy::class);
        Gate::policy(VendorBankAccount::class, VendorBankAccountPolicy::class);
        Gate::policy(ReturnRequest::class, ReturnRequestPolicy::class);
        Gate::policy(VendorListing::class, VendorListingPolicy::class);
        Gate::policy(WarehouseInventory::class, WarehouseInventoryPolicy::class);
        Gate::policy(DeliveryAssignment::class, DeliveryAssignmentPolicy::class);
        Gate::policy(ShippingCompanySupervisor::class, SupervisorPolicy::class);
        Gate::policy(DeliveryAgent::class, CarrierAgentPolicy::class);
        Gate::policy(ClassifiedListing::class, ClassifiedListingPolicy::class);
        Gate::policy(ClassifiedInquiry::class, ClassifiedInquiryPolicy::class);
        Gate::policy(PaidAdSlot::class, AdSlotPolicy::class);

        VendorListing::observe(VendorListingObserver::class);
        AdminListing::observe(AdminListingObserver::class);
        WarrantyPurchase::observe(WarrantyPurchaseObserver::class);
        SubOrder::observe(SubOrderObserver::class);
        Coupon::observe(CouponObserver::class);

        Event::listen(SubOrderPlaced::class, InvalidateVendorDashboardCache::class);

        \Illuminate\Support\Facades\Notification::extend('push', function ($app) {
            return $app->make(VendorPushChannel::class);
        });

        // Hyphenated aliases used by admin Blade views (x-form-input etc.).
        Blade::component(Input::class, 'form-input');
        Blade::component(Select::class, 'form-select');
        Blade::component(PriceInput::class, 'form-price-input');
        Blade::component(AsyncSelect::class, 'form-async-select');
        Blade::component(RichEditor::class, 'form-rich-editor');
        Blade::component(FileUpload::class, 'form-file-upload');

        // x-file-upload (shorthand alias used in flash-sales view).
        Blade::component(FileUpload::class, 'file-upload');

        // Anonymous form components without a class-based counterpart.
        Blade::component('components.form.toggle', 'form-toggle');
        Blade::component('components.form.slug-input', 'form-slug-input');
        Blade::component('components.form.checkbox-group', 'form-checkbox-group');
        Blade::component('components.form.textarea', 'form-textarea');
        Blade::component('components.form.date-picker', 'form-date-picker');
        Blade::component('components.form.radio-group', 'form-radio-group');

        // Portal header country dropdown: shares the active/launched country list plus
        // the currently selected one (URL {country} segment, then session, then default).
        View::composer('portal.partials.nav', function ($view): void {
            $countries = Country::where('is_active', true)
                ->where('is_launched', true)
                ->orderBy('name_en')
                ->get();

            $currentCode = Country::resolveSiteCode(request()->route('country'));
            $currentCountry = $countries->firstWhere('site_code', $currentCode) ?? $countries->first();

            $view->with(compact('countries', 'currentCountry'));
        });

        // Travel agency portal: shares the agency/owner-vs-member context and
        // pending-bookings / new-inquiries / open-tickets badge counts (cached 60s
        // per agency) with every view under the travel-agency.* namespace, including
        // the layout's sidebar.
        View::composer('travel-agency.*', TravelAgencySidebarComposer::class);
        View::composer('layouts.travel-agency', TravelAgencySidebarComposer::class);

        View::composer('*', SettingsComposer::class);

        // Product detail routes: /products/{productSlug}/{variantSlug}
        // Binds the active Product by slug, then the active, non-deleted variant
        // belonging to that product. 404s early instead of leaking lookups into
        // every controller action that touches these routes.
        Route::bind('productSlug', function (string $value): Product {
            return Product::where('slug', $value)
                ->where('status', 'active')
                ->firstOrFail();
        });

        Route::bind('variantSlug', function (string $value, $route): ProductVariant {
            $product = $route->parameter('productSlug');

            if (! $product instanceof Product) {
                $product = Product::where('slug', $product)
                    ->where('status', 'active')
                    ->firstOrFail();
            }

            $variant = ProductVariant::where('product_id', $product->id)
                ->where('slug', $value)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->first();

            if (! $variant) {
                abort(404, 'Variant not found');
            }

            return $variant;
        });
    }
}
