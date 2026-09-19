<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\CustomerSpecialRequest;
use App\Models\MarketerProfile;
use Illuminate\Support\Facades\Auth;

class SpecialRequestController extends Controller
{
    private function profile(): MarketerProfile
    {
        $marketer = Auth::guard('marketer_api')->user()->marketer;

        abort_unless($marketer->isAffiliate() && $marketer->global_status?->value === 'active', 403);

        return $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);
    }

    /** Never expose customer email/phone: first name only. */
    private function transform(CustomerSpecialRequest $r): array
    {
        return [
            'id'              => $r->id,
            'title_ar'        => $r->title_ar,
            'title_en'        => $r->title_en,
            'description_ar'  => $r->description_ar,
            'description_en'  => $r->description_en,
            'budget'          => $r->budget,
            'budget_currency' => $r->budget_currency,
            'status'          => $r->status,
            'category'        => $r->category ? ['id' => $r->category->id, 'name_ar' => $r->category->name_ar, 'name_en' => $r->category->name_en] : null,
            'city'            => $r->city ? ['id' => $r->city->id, 'name_ar' => $r->city->name_ar, 'name_en' => $r->city->name_en] : null,
            'customer_first_name' => \Illuminate\Support\Str::before(trim((string) ($r->customer->name ?? '')), ' ') ?: null,
            'created_at'      => $r->created_at,
        ];
    }

    public function index()
    {
        $profile = $this->profile();

        $requests = CustomerSpecialRequest::matchingBroker($profile)
            ->with(['customer', 'category', 'city'])
            ->latest()
            ->paginate(20);

        $requests->getCollection()->transform(fn ($r) => $this->transform($r));

        return response()->json([
            'success'            => true,
            'has_specialization' => (bool) $profile->broker_category_id,
            'data'               => $requests,
        ]);
    }

    public function show(string $id)
    {
        $profile = $this->profile();

        $request = CustomerSpecialRequest::matchingBroker($profile)
            ->with(['customer', 'category', 'city'])
            ->where('id', $id)
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $this->transform($request)]);
    }
}
