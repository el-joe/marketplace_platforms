<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use Illuminate\View\View;

/**
 * Public seller-portal rendering of admin-managed static pages (audience portal|both).
 */
class StaticPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = StaticPage::query()
            ->forPortal()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('portal.static-page', ['page' => $page]);
    }
}
