<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AiFeatureCreditOwnerType;
use App\Enums\AiImageEnhancementJobStatus;
use App\Enums\AiVideoGenerationJobStatus;
use App\Enums\VirtualTryonSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\AiFeatureCredit;
use App\Models\AiImageEnhancementJob;
use App\Models\AiVideoGenerationJob;
use App\Models\VirtualTryonSession;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiDashboardController extends Controller
{
    public function index(): View
    {
        $imageStats = [
            'queued'     => AiImageEnhancementJob::where('status', AiImageEnhancementJobStatus::Queued)->count(),
            'processing' => AiImageEnhancementJob::where('status', AiImageEnhancementJobStatus::Processing)->count(),
            'completed'  => AiImageEnhancementJob::where('status', AiImageEnhancementJobStatus::Completed)->count(),
            'failed'     => AiImageEnhancementJob::where('status', AiImageEnhancementJobStatus::Failed)->count(),
        ];

        $tryonStats = [
            'queued'     => VirtualTryonSession::where('status', VirtualTryonSessionStatus::Queued)->count(),
            'processing' => VirtualTryonSession::where('status', VirtualTryonSessionStatus::Processing)->count(),
            'completed'  => VirtualTryonSession::where('status', VirtualTryonSessionStatus::Completed)->count(),
            'failed'     => VirtualTryonSession::where('status', VirtualTryonSessionStatus::Failed)->count(),
        ];

        $videoStats = [
            'queued'     => AiVideoGenerationJob::where('status', AiVideoGenerationJobStatus::Queued)->count(),
            'processing' => AiVideoGenerationJob::where('status', AiVideoGenerationJobStatus::Processing)->count(),
            'completed'  => AiVideoGenerationJob::where('status', AiVideoGenerationJobStatus::Completed)->count(),
            'failed'     => AiVideoGenerationJob::where('status', AiVideoGenerationJobStatus::Failed)->count(),
        ];

        $recentFailures = collect()
            ->merge(AiImageEnhancementJob::where('status', AiImageEnhancementJobStatus::Failed)->latest()->limit(5)->get()->map(fn($j) => ['type' => 'image_enhancement', 'id' => $j->id, 'error' => $j->error_message, 'at' => $j->updated_at]))
            ->merge(VirtualTryonSession::where('status', VirtualTryonSessionStatus::Failed)->latest()->limit(5)->get()->map(fn($j) => ['type' => 'virtual_tryon', 'id' => $j->id, 'error' => $j->error_message, 'at' => $j->updated_at]))
            ->merge(AiVideoGenerationJob::where('status', AiVideoGenerationJobStatus::Failed)->latest()->limit(5)->get()->map(fn($j) => ['type' => 'video_generation', 'id' => $j->id, 'error' => $j->error_message, 'at' => $j->updated_at]))
            ->sortByDesc('at')
            ->take(10)
            ->values();

        $creditSummary = AiFeatureCredit::query()
            ->selectRaw('feature, SUM(credits_remaining) as total_remaining, SUM(credits_used_total) as total_used')
            ->groupBy('feature')
            ->get()
            ->keyBy('feature');

        return view('admin.ai-dashboard.index', compact(
            'imageStats', 'tryonStats', 'videoStats', 'recentFailures', 'creditSummary'
        ));
    }

    public function allocateCredits(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'owner_type' => ['required', Rule::enum(AiFeatureCreditOwnerType::class)],
            'owner_id'   => ['required', 'uuid'],
            'feature'    => ['required', 'in:image_enhancement,virtual_tryon,video_generation'],
            'amount'     => ['required', 'integer', 'min:1', 'max:10000'],
            'reset_at'   => ['nullable', 'date'],
        ]);

        $credit = AiFeatureCredit::balanceFor(
            $validated['owner_type'],
            $validated['owner_id'],
            $validated['feature']
        );

        $credit->topUp($validated['amount']);

        if (! empty($validated['reset_at'])) {
            $credit->update(['reset_at' => $validated['reset_at']]);
        }

        return back()->with('success', __('admin.ai_dashboard_section.credits_added', [
            'amount' => $validated['amount'],
            'feature' => $validated['feature'],
        ]));
    }
}
