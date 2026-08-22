<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('portal.home');
    }

    public function faq(): View
    {
        return view('portal.faq');
    }

    public function howItWorks(): View
    {
        return view('portal.how-it-works');
    }

    public function fulfillment(): View
    {
        return view('portal.fulfillment');
    }

    public function smartTools(): View
    {
        return view('portal.smart-tools');
    }

    public function sellers(?string $country = null): View
    {
        $country = Country::resolveSiteCode($country);

        return view('portal.sellers', compact('country'));
    }

    public function advertiseBrands(?string $country = null): View
    {
        $country = Country::resolveSiteCode($country);

        return view('portal.advertise-brands', compact('country'));
    }

    public function advertiseRequest(?string $country = null): View
    {
        $country = Country::resolveSiteCode($country);

        return view('portal.advertise-request', compact('country'));
    }

    public function advertiseAdvertisers(?string $country = null): View
    {
        $country = Country::resolveSiteCode($country);

        return view('portal.advertise-advertisers', compact('country'));
    }

    public function advertiseProduct(?string $country = null): View
    {
        $country = Country::resolveSiteCode($country);

        return view('portal.advertise-product', compact('country'));
    }

    public function advertiseDisplay(?string $country = null): View
    {
        $country = Country::resolveSiteCode($country);

        return view('portal.advertise-display', compact('country'));
    }
}
