<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PromotionCategory;
use App\Http\Controllers\Controller;
use App\Models\MarketerPromotionFine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\Marketer\PromotionPenaltyService;

class MarketerPromotionFineController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([MarketerPromotionFine::PENDING, MarketerPromotionFine::DEDUCTED, MarketerPromotionFine::WAIVED])],
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'category' => ['nullable', Rule::enum(PromotionCategory::class)],
        ]);

        $fines = MarketerPromotionFine::query()->with('marketer')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['period'] ?? null, fn ($q, $v) => $q->where('period', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('promotion_category', $v))
            ->orderByDesc('period')->latest()
            ->paginate(25)->withQueryString();

        $totals = MarketerPromotionFine::query()
            ->selectRaw('status, COUNT(*) as n, SUM(amount) as total')->groupBy('status')->get()->keyBy('status');

        return view('admin.marketer_fines.index', compact('fines', 'totals', 'filters'));
    }

    public function waive(Request $request, MarketerPromotionFine $fine, PromotionPenaltyService $penalties): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $ok = $penalties->waive($fine, auth('admin')->id(), $data['note'] ?? null);

        return back()->with($ok ? 'success' : 'error', $ok ? __('admin.marketer_fines.waived') : __('admin.marketer_fines.cannot_waive'));
    }
}
