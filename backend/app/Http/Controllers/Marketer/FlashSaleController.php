<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\FlashSaleMarketerInvitation;
use App\Models\Marketer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FlashSaleController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    public function index(): View
    {
        $marketer = $this->marketer();

        $pending = FlashSaleMarketerInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'pending')
            ->with('flashSale')
            ->latest()
            ->get();

        $active = FlashSaleMarketerInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereHas('flashSale', fn ($q) => $q->where('status', 'live'))
            ->with('flashSale')
            ->latest()
            ->get();

        $past = FlashSaleMarketerInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereHas('flashSale', fn ($q) => $q->whereIn('status', ['ended', 'cancelled']))
            ->with(['flashSale', 'flashSale.orders'])
            ->latest()
            ->get()
            ->map(function ($invitation) {
                $invitation->conversions_earned = \App\Models\MarketerCampaignConversion::where('flash_sale_id', $invitation->flash_sale_id)
                    ->whereHas('invitation', fn ($q) => $q->where('marketer_id', $invitation->marketer_id))
                    ->count();
                $invitation->bonus_earned = \App\Models\MarketerCampaignConversion::where('flash_sale_id', $invitation->flash_sale_id)
                    ->whereHas('invitation', fn ($q) => $q->where('marketer_id', $invitation->marketer_id))
                    ->sum('flash_sale_bonus_amount');
                return $invitation;
            });

        return view('marketer.flash-sales.index', compact('pending', 'active', 'past'));
    }

    public function accept(FlashSaleMarketerInvitation $invitation): RedirectResponse
    {
        $this->authorizeInvitation($invitation);

        $invitation->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        return back()->with('success', 'تم قبول دعوة التخفيضات السريعة');
    }

    public function decline(FlashSaleMarketerInvitation $invitation): RedirectResponse
    {
        $this->authorizeInvitation($invitation);

        $invitation->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        return back()->with('success', 'تم رفض دعوة التخفيضات السريعة');
    }

    private function authorizeInvitation(FlashSaleMarketerInvitation $invitation): void
    {
        abort_if($invitation->marketer_id !== $this->marketer()->id, 403);
    }
}
