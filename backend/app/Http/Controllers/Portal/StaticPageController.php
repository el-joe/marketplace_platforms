<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\StaticPage;
use Illuminate\View\View;

/**
 * Public seller-portal rendering of admin-managed static pages (audience portal|both),
 * resolved for the visitor's portal country (its own page, else the global one).
 */
class StaticPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = StaticPage::resolveForCountry($slug, StaticPage::portalCountryId(), StaticPage::AUDIENCE_PORTAL);

        abort_if($page === null, 404);

        return view('portal.static-page', ['page' => $page]);
    }
}
