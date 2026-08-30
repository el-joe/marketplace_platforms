<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCostReference;
use App\Models\VendorListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductCostController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Show / Load
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the cost reference record for a product (or null if none exists).
     * Used by the admin product edit tab via AJAX.
     */
    public function show(string $productId): JsonResponse
    {
        $this->requireViewPermission();

        $product = Product::where('id', $productId)->whereNull('deleted_at')->firstOrFail();
        $ref = ProductCostReference::where('product_id', $productId)->first();

        // Lowest active vendor listing price — used for margin alert
        $lowestPriceCents = VendorListing::query()
            ->whereHas('productVariant', fn($q) => $q->where('product_id', $productId))
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->min('price');

        $belowCost = false;
        if ($ref && $lowestPriceCents) {
            $belowCost = $ref->isBelowLandedCost((int) $lowestPriceCents);
        }

        return response()->json([
            'product_id' => $productId,
            'product_name' => $product->name_en,
            'ref' => $ref ? $this->serializeRef($ref) : null,
            'lowest_price' => $lowestPriceCents ? (int) $lowestPriceCents : null,
            'below_cost_warning' => $belowCost,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Save (create or update)
    // ─────────────────────────────────────────────────────────────────────────

    public function save(Request $request, string $productId): JsonResponse
    {
        $this->requireEditPermission();

        Product::where('id', $productId)->whereNull('deleted_at')->firstOrFail();

        $data = $request->validate([
            'vendor_listing_id' => ['nullable', 'uuid', 'exists:vendor_listings,id'],
            'manufacturer_name' => ['nullable', 'string', 'max:255'],
            'manufacturer_url' => ['nullable', 'url', 'max:500'],
            'manufacturer_sku' => ['nullable', 'string', 'max:100'],
            'manufacturer_cost' => ['nullable', 'integer', 'min:0'],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'landed_cost' => ['nullable', 'integer', 'min:0'],
            'platform_margin_pct' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'competitor_links' => ['nullable', 'array'],
            'competitor_links.*.name' => ['required_with:competitor_links', 'string', 'max:255'],
            'competitor_links.*.url' => ['required_with:competitor_links', 'url', 'max:500'],
            'competitor_links.*.price' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $adminId = Auth::guard('admin')->id();

        /** @var ProductCostReference|null $ref */
        $ref = ProductCostReference::where('product_id', $productId)->first();

        if ($ref) {
            $ref->fill(array_merge($data, ['updated_by_admin_id' => $adminId]));
            $ref->save();
            $message = 'Cost reference updated.';
        } else {
            $ref = ProductCostReference::create(array_merge($data, [
                'product_id' => $productId,
                'is_confidential' => 1,
                'created_by_admin_id' => $adminId,
            ]));
            $message = 'Cost reference created.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'ref' => $this->serializeRef($ref->fresh()),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Margin Calculator
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Stateless margin calculator: given a price and costs, return margin info.
     * Does not require a saved record.
     */
    public function calculateMargin(Request $request): JsonResponse
    {
        $this->requireViewPermission();

        $data = $request->validate([
            'selling_price' => ['required', 'integer', 'min:1'],
            'manufacturer_cost' => ['nullable', 'integer', 'min:0'],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'landed_cost' => ['nullable', 'integer', 'min:0'],
        ]);

        $selling = (int) $data['selling_price'];

        // landed_cost takes precedence; otherwise sum components
        $landed = isset($data['landed_cost'])
            ? (int) $data['landed_cost']
            : ((int) ($data['manufacturer_cost'] ?? 0)) + ((int) ($data['shipping_cost'] ?? 0));

        if ($landed <= 0) {
            return response()->json(['error' => 'Landed cost must be > 0.'], 422);
        }

        $marginPct = round(($selling - $landed) / $selling * 100, 2);
        $profit = $selling - $landed;
        $belowCost = $selling <= $landed;

        return response()->json([
            'selling_price' => $selling,
            'landed_cost' => $landed,
            'profit' => $profit,
            'margin_pct' => $marginPct,
            'below_cost' => $belowCost,
            'selling_formatted' => number_format($selling, 2) . ' EGP',
            'landed_formatted' => number_format($landed, 2) . ' EGP',
            'profit_formatted' => number_format($profit, 2) . ' EGP',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Competitor Price Check
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch competitor pages and attempt to extract prices from
     * JSON-LD structured data (schema.org/Product → offers.price).
     * Updates the competitor_links array for the given product.
     */
    public function checkCompetitorPrices(Request $request, string $productId): JsonResponse
    {
        $this->requireEditPermission();

        $ref = ProductCostReference::where('product_id', $productId)->firstOrFail();

        $links = $ref->competitorLinksNormalized();
        if (empty($links)) {
            return response()->json(['success' => false, 'message' => 'No competitor links configured.'], 422);
        }

        $updated = 0;
        $checkedAt = now()->toISOString();

        foreach ($links as &$link) {
            if (empty($link['url'])) {
                continue;
            }

            try {
                $response = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; PlatformBot/1.0)'])
                    ->get($link['url']);

                if (!$response->ok()) {
                    $link['last_checked'] = $checkedAt;
                    continue;
                }

                $html = $response->body();
                $price = $this->extractPriceFromHtml($html);

                if ($price !== null) {
                    $link['price'] = (int) round($price);
                    $updated++;
                }
                $link['last_checked'] = $checkedAt;
            } catch (\Throwable $e) {
                Log::warning('Competitor price check failed', [
                    'url' => $link['url'],
                    'error' => $e->getMessage(),
                ]);
                $link['last_checked'] = $checkedAt;
            }
        }
        unset($link);

        $ref->competitor_links = $links;
        $ref->competitor_last_checked = now();
        $ref->updated_by_admin_id = Auth::guard('admin')->id();
        $ref->save();

        return response()->json([
            'success' => true,
            'message' => "Checked {$updated} price(s) via structured data.",
            'links' => $links,
            'checked_at' => $checkedAt,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function requireViewPermission(): void
    {
        abort_unless(
            Auth::guard('admin')->user()?->hasPermissionTo('products.cost_data.view'),
            403,
            'You do not have permission to view product cost data.'
        );
    }

    private function requireEditPermission(): void
    {
        abort_unless(
            Auth::guard('admin')->user()?->hasPermissionTo('products.cost_data.edit'),
            403,
            'You do not have permission to edit product cost data.'
        );
    }

    /**
     * Extract price from HTML via schema.org JSON-LD or Open Graph meta tags.
     */
    private function extractPriceFromHtml(string $html): ?float
    {
        // 1. Try JSON-LD (schema.org Product / Offer)
        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $m)) {
            foreach ($m[1] as $json) {
                try {
                    $data = json_decode(trim($json), true, 512, JSON_THROW_ON_ERROR);
                    // Handle @graph arrays
                    $nodes = isset($data['@graph']) ? $data['@graph'] : [$data];
                    foreach ($nodes as $node) {
                        if (isset($node['offers'])) {
                            $offers = is_array($node['offers']) && isset($node['offers'][0])
                                ? $node['offers']
                                : [$node['offers']];
                            foreach ($offers as $offer) {
                                if (isset($offer['price']) && is_numeric($offer['price'])) {
                                    return (float) $offer['price'];
                                }
                            }
                        }
                    }
                } catch (\Throwable) {
                    // malformed JSON — skip
                }
            }
        }

        // 2. Try Open Graph og:price:amount
        if (preg_match('/<meta[^>]+property=["\']product:price:amount["\'][^>]+content=["\']([0-9.,]+)["\']/', $html, $m)) {
            $val = str_replace(',', '', $m[1]);
            if (is_numeric($val)) {
                return (float) $val;
            }
        }

        return null;
    }

    private function serializeRef(ProductCostReference $ref): array
    {
        return [
            'id' => $ref->id,
            'product_id' => $ref->product_id,
            'vendor_listing_id' => $ref->vendor_listing_id,
            'manufacturer_name' => $ref->manufacturer_name,
            'manufacturer_url' => $ref->manufacturer_url,
            'manufacturer_sku' => $ref->manufacturer_sku,
            'manufacturer_cost' => $ref->manufacturer_cost,
            'shipping_cost' => $ref->shipping_cost,
            'landed_cost' => $ref->landed_cost,
            'platform_margin_pct' => $ref->platform_margin_pct,
            'competitor_links' => $ref->competitorLinksNormalized(),
            'competitor_last_checked' => $ref->competitor_last_checked?->toISOString(),
            'notes' => $ref->notes,
            'created_by' => $ref->createdByAdmin?->name ?? 'System',
            'updated_by' => $ref->updatedByAdmin?->name ?? null,
            'updated_at' => $ref->updated_at?->toISOString(),
            // formatted helpers
            'manufacturer_cost_formatted' => $ref->manufacturerCostFormatted(),
            'shipping_cost_formatted' => $ref->shippingCostFormatted(),
            'landed_cost_formatted' => $ref->landedCostFormatted(),
        ];
    }
}
