<?php

namespace App\Http\Controllers\Marketer;

use App\Enums\ClassifiedInquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassifiedInquiry;
use App\Models\Marketer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClassifiedInquiryController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    private function ownedQuery()
    {
        $marketer = $this->marketer();

        return ClassifiedInquiry::query()->whereHas('listing', fn ($q) => $q
            ->withTrashed()
            ->where('seller_type', Marketer::class)
            ->where('seller_id', $marketer->id));
    }

    public function index(): View
    {
        $inquiries = $this->ownedQuery()->with(['listing' => fn ($q) => $q->withTrashed(), 'customer'])->latest()->paginate(20);

        return view('marketer.classified-inquiries.index', compact('inquiries'));
    }

    public function show(string $inquiry): View
    {
        $inquiry = $this->ownedQuery()->with(['listing' => fn ($q) => $q->withTrashed(), 'customer'])->findOrFail($inquiry);

        if ($inquiry->status === ClassifiedInquiryStatus::New) {
            $inquiry->update(['status' => ClassifiedInquiryStatus::Contacted]);
        }

        return view('marketer.classified-inquiries.show', compact('inquiry'));
    }

    public function close(string $inquiry): RedirectResponse
    {
        $inquiry = $this->ownedQuery()->findOrFail($inquiry);
        $inquiry->update(['status' => ClassifiedInquiryStatus::Closed]);

        return back()->with('success', __('marketer.inquiry_closed'));
    }
}
