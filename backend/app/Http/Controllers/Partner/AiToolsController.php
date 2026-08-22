<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImageEnhancementJob;
use App\Jobs\ProcessVideoGenerationJob;
use App\Models\AiFeatureCredit;
use App\Models\AiImageEnhancementJob;
use App\Models\AiVideoGenerationJob;
use App\Models\ProductImage;
use App\Models\VendorListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiToolsController extends Controller
{
    private function vendor()
    {
        return Auth::guard('vendor')->user();
    }

    public function index(VendorListing $listing): \Illuminate\View\View
    {
        $vendor = $this->vendor();

        abort_unless($listing->vendor_id === $vendor->vendor_id, 403);

        $listing->load('productVariant.images');

        $credits = [];
        foreach ([AiFeatureCredit::FEATURE_IMAGE_ENHANCEMENT, AiFeatureCredit::FEATURE_VIDEO_GENERATION] as $feature) {
            $credits[$feature] = AiFeatureCredit::balanceFor('vendor', $vendor->vendor_id, $feature);
        }

        return view('partner.ai-tools.index', compact('listing', 'credits'));
    }

    // ── Image Enhancement ─────────────────────────────────────────────────

    public function enhanceImage(VendorListing $listing, ProductImage $image): JsonResponse
    {
        $vendor = $this->vendor();

        // Ensure the listing belongs to this vendor
        if ($listing->vendor_id !== $vendor->vendor_id) {
            return response()->json(['error' => __('partner.ai_tools.messages.unauthorized')], 403);
        }

        $credit = AiFeatureCredit::balanceFor('vendor', $vendor->vendor_id, AiFeatureCredit::FEATURE_IMAGE_ENHANCEMENT);

        if (! $credit->hasCredits()) {
            return response()->json(['error' => __('partner.ai_tools.messages.no_image_credits')], 422);
        }

        $credit->consume();

        $job = AiImageEnhancementJob::create([
            'product_image_id'  => $image->id,
            'original_path'     => $image->path,
            'status'            => 'queued',
            'requested_by_type' => 'vendor',
            'requested_by_id'   => $vendor->vendor_id,
        ]);

        ProcessImageEnhancementJob::dispatch($job)->onQueue('ai');

        return response()->json([
            'job_id'  => $job->id,
            'status'  => 'queued',
            'message' => __('partner.ai_tools.messages.image_enhancement_queued'),
        ]);
    }

    public function checkEnhancementStatus(string $jobId): JsonResponse
    {
        $vendor = $this->vendor();

        $job = AiImageEnhancementJob::where('id', $jobId)
            ->where('requested_by_type', 'vendor')
            ->where('requested_by_id', $vendor->vendor_id)
            ->firstOrFail();

        return response()->json([
            'status'        => $job->status->value,
            'enhanced_path' => $job->enhanced_path ? asset('storage/' . $job->enhanced_path) : null,
            'original_path' => asset('storage/' . $job->original_path),
            'applied'       => $job->applied,
            'error'         => $job->error_message,
        ]);
    }

    public function applyEnhancement(string $jobId): JsonResponse
    {
        $vendor = $this->vendor();

        $job = AiImageEnhancementJob::where('id', $jobId)
            ->where('requested_by_type', 'vendor')
            ->where('requested_by_id', $vendor->vendor_id)
            ->where('status', 'completed')
            ->firstOrFail();

        // TODO: copy enhanced_path over to the product_image record's path field
        // $job->productImage->update(['path' => $job->enhanced_path]);

        $job->update(['applied' => true]);

        return response()->json(['message' => __('partner.ai_tools.messages.enhanced_applied')]);
    }

    // ── Video Generation ──────────────────────────────────────────────────

    public function generateVideo(Request $request): JsonResponse
    {
        $vendor = $this->vendor();

        $validated = $request->validate([
            'prompt'            => ['required', 'string', 'max:1000'],
            'vendor_listing_id' => ['nullable', 'uuid', 'exists:vendor_listings,id'],
            'source_images'     => ['nullable', 'array'],
            'source_images.*'   => ['string'],
        ]);

        $credit = AiFeatureCredit::balanceFor('vendor', $vendor->vendor_id, AiFeatureCredit::FEATURE_VIDEO_GENERATION);

        if (! $credit->hasCredits()) {
            return response()->json(['error' => __('partner.ai_tools.messages.no_video_credits')], 422);
        }

        $credit->consume();

        $videoJob = AiVideoGenerationJob::create([
            'requested_by_type' => 'vendor',
            'requested_by_id'   => $vendor->vendor_id,
            'vendor_listing_id' => $validated['vendor_listing_id'] ?? null,
            'prompt'            => $validated['prompt'],
            'source_images'     => $validated['source_images'] ?? [],
            'status'            => 'queued',
            'credits_consumed'  => 1,
        ]);

        ProcessVideoGenerationJob::dispatch($videoJob)->onQueue('ai');

        return response()->json([
            'job_id'  => $videoJob->id,
            'status'  => 'queued',
            'message' => __('partner.ai_tools.messages.video_generation_queued'),
        ]);
    }

    public function checkVideoStatus(string $jobId): JsonResponse
    {
        $vendor = $this->vendor();

        $job = AiVideoGenerationJob::where('id', $jobId)
            ->where('requested_by_type', 'vendor')
            ->where('requested_by_id', $vendor->vendor_id)
            ->firstOrFail();

        return response()->json([
            'status'     => $job->status->value,
            'video_url'  => $job->result_video_path ? asset('storage/' . $job->result_video_path) : null,
            'error'      => $job->error_message,
        ]);
    }

    // ── Credits overview ──────────────────────────────────────────────────

    public function credits(): JsonResponse
    {
        $vendor = $this->vendor();

        $features = [
            AiFeatureCredit::FEATURE_IMAGE_ENHANCEMENT,
            AiFeatureCredit::FEATURE_VIRTUAL_TRYON,
            AiFeatureCredit::FEATURE_VIDEO_GENERATION,
        ];

        $data = [];
        foreach ($features as $feature) {
            $credit = AiFeatureCredit::balanceFor('vendor', $vendor->vendor_id, $feature);
            $data[$feature] = [
                'remaining' => $credit->credits_remaining,
                'used_total' => $credit->credits_used_total,
                'reset_at'  => $credit->reset_at?->toDateString(),
            ];
        }

        return response()->json($data);
    }
}
