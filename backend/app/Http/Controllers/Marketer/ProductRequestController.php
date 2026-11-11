<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Concerns\HandlesPanelProductRequests;
use App\Http\Controllers\Controller;
use App\Models\MarketerAdmin;
use App\Models\ProductRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Marketers request catalog products that are missing when adding a listing.
 */
class ProductRequestController extends Controller
{
    use HandlesPanelProductRequests;

    protected function panelUser(): MarketerAdmin
    {
        return Auth::guard('marketer')->user();
    }

    protected function teamRequests(): Builder
    {
        return ProductRequest::query()->forMarketer($this->panelUser()->marketer_id);
    }

    protected function panel(): string
    {
        return 'marketer';
    }

    protected function defaultCountryId(): ?string
    {
        return $this->panelUser()->marketer?->country_id;
    }
}
