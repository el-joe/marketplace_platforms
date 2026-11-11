<?php

namespace App\Notifications\Concerns;

use App\Models\MarketerAdmin;
use App\Models\ProductRequest;
use Illuminate\Support\Facades\Route;

/**
 * Builds the requester-panel link for a product request: the partner panel for
 * vendor requests, the marketer panel for marketer requests. Falls back to the
 * panel dashboard while the panel's request pages are not registered.
 */
trait ResolvesProductRequestUrl
{
    private function requesterPanelUrl(ProductRequest $productRequest): string
    {
        [$showRoute, $fallbackRoute] = $productRequest->isFromMarketer()
            ? ['marketer.product-requests.show', 'marketer.dashboard']
            : ['partner.product-requests.show', 'partner.dashboard'];

        return Route::has($showRoute)
            ? route($showRoute, $productRequest->id)
            : route($fallbackRoute);
    }

    /**
     * Private broadcast channel of a VendorAdmin (vendor.{id}) or MarketerAdmin (marketer.{id}).
     */
    private static function requesterChannelName(object $notifiable): string
    {
        return ($notifiable instanceof MarketerAdmin ? 'marketer.' : 'vendor.').$notifiable->getKey();
    }
}
