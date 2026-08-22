<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TravelAgencyStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\TravelAgency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelAgencyController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $pendingCount = TravelAgency::where('status', TravelAgencyStatus::Pending)->count();
        $countries    = Country::orderBy('name_en')->get(['id', 'name_en']);

        return view('admin.travel-agencies.index', compact('pendingCount', 'countries'));
    }

    // ── DataTable ─────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $query = TravelAgency::query()->with('country');

        if ($s = $request->input('status')) {
            $query->where('status', $s);
        }

        if ($c = $request->input('country_id')) {
            $query->where('country_id', $c);
        }

        if ($q = $request->input('search')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $agencies = $query->latest()->paginate(25);

        return response()->json([
            'data' => $agencies->map(fn(TravelAgency $a) => [
                'id'           => $a->id,
                'name'         => $a->name,
                'email'        => $a->email,
                'country'      => $a->country?->name_en,
                'status'       => $a->status?->value,
                'packages'     => $a->packages()->count(),
                'approved_at'  => $a->approved_at?->format('d M Y'),
                'created_at'   => $a->created_at->format('d M Y'),
            ]),
            'meta' => [
                'total'        => $agencies->total(),
                'current_page' => $agencies->currentPage(),
                'last_page'    => $agencies->lastPage(),
            ],
        ]);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(TravelAgency $travelAgency)
    {
        $travelAgency->load(['country', 'approvedByAdmin', 'packages']);

        return view('admin.travel-agencies.show', compact('travelAgency'));
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    public function approve(TravelAgency $travelAgency): JsonResponse
    {
        $travelAgency->update([
            'status'               => TravelAgencyStatus::Active,
            'approved_by_admin_id' => auth('admin')->id(),
            'approved_at'          => now(),
        ]);

        return response()->json(['message' => 'Travel agency approved.']);
    }

    // ── Suspend ───────────────────────────────────────────────────────────────

    public function suspend(Request $request, TravelAgency $travelAgency): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $travelAgency->update(['status' => TravelAgencyStatus::Suspended]);

        return response()->json(['message' => 'Travel agency suspended.']);
    }

    // ── Reactivate ────────────────────────────────────────────────────────────

    public function reactivate(TravelAgency $travelAgency): JsonResponse
    {
        $travelAgency->update(['status' => TravelAgencyStatus::Active]);

        return response()->json(['message' => 'Travel agency reactivated.']);
    }

    // ── Reject (delete pending) ───────────────────────────────────────────────

    public function reject(Request $request, TravelAgency $travelAgency): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $travelAgency->delete();

        return response()->json(['message' => 'Travel agency rejected and removed.']);
    }
}
