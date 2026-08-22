<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DocsController extends Controller
{
    public function index(): View { return view('admin.docs.index'); }

    // ── PANELS ──
    public function adminPanel(): View          { return view('admin.docs.panels.admin'); }
    public function partnerPanel(): View        { return view('admin.docs.panels.partner'); }
    public function marketerPanel(): View       { return view('admin.docs.panels.marketer'); }
    public function travelPanel(): View         { return view('admin.docs.panels.travel'); }
    public function deliveryPanel(): View       { return view('admin.docs.panels.delivery'); }
    public function carrierPanel(): View        { return view('admin.docs.panels.carrier'); }

    // ── FEATURES ──
    public function orderLifecycle(): View      { return view('admin.docs.features.order-lifecycle'); }
    public function shipping(): View            { return view('admin.docs.features.shipping'); }
    public function warehouses(): View          { return view('admin.docs.features.warehouses'); }
    public function payments(): View            { return view('admin.docs.features.payments'); }
    public function pageBuilder(): View         { return view('admin.docs.features.page-builder'); }
    public function banners(): View             { return view('admin.docs.features.banners'); }
    public function adCampaigns(): View         { return view('admin.docs.features.ad-campaigns'); }
    public function marketerCampaigns(): View   { return view('admin.docs.features.marketer-campaigns'); }
    public function vendorCampaigns(): View     { return view('admin.docs.features.vendor-campaigns'); }
    public function influencerDeals(): View     { return view('admin.docs.features.influencer-deals'); }
    public function secretPromotions(): View    { return view('admin.docs.features.secret-promotions'); }
    public function affiliateCodes(): View      { return view('admin.docs.features.affiliate-codes'); }
    public function flashSales(): View          { return view('admin.docs.features.flash-sales'); }
    public function finance(): View             { return view('admin.docs.features.finance'); }
    public function subsidy(): View             { return view('admin.docs.features.subsidy'); }
    public function packaging(): View           { return view('admin.docs.features.packaging'); }
    public function contentPages(): View        { return view('admin.docs.features.content-pages'); }
    public function systemPages(): View         { return view('admin.docs.features.system-pages'); }
    public function roles(): View               { return view('admin.docs.features.roles'); }
    public function warranties(): View          { return view('admin.docs.features.warranties'); }
    public function classifieds(): View         { return view('admin.docs.features.classifieds'); }
    public function travelFeature(): View       { return view('admin.docs.features.travel'); }
    public function radioFeature(): View        { return view('admin.docs.features.radio'); }
}
