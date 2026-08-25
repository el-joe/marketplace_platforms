<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\WarrantyClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarrantyClaimController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $claims = WarrantyClaim::where('vendor_id', $vendorId)
            ->with(['customer:id,first_name,last_name', 'warrantyPurchase'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(20);

        return ApiResponse::success([
            'data' => $claims->map(fn ($c) => [
                'id'           => $c->id,
                'claim_number' => $c->claim_number,
                'status'       => $c->status,
                'issue_type'   => $c->issue_type,
                'description'  => $c->description,
                'customer'     => $c->customer ? ['id' => $c->customer->id, 'name' => $c->customer->first_name . ' ' . $c->customer->last_name] : null,
                'created_at'   => $c->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $claims->currentPage(), 'last_page' => $claims->lastPage(), 'total' => $claims->total()],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $claim = WarrantyClaim::where('id', $id)
            ->where('vendor_id', $vendorId)
            ->with(['customer:id,first_name,last_name', 'warrantyPurchase', 'messages'])
            ->firstOrFail();

        return ApiResponse::success([
            'id'              => $claim->id,
            'claim_number'    => $claim->claim_number,
            'status'          => $claim->status,
            'issue_type'      => $claim->issue_type,
            'description'     => $claim->description,
            'resolution_notes'=> $claim->resolution_notes,
            'customer'        => $claim->customer ? ['id' => $claim->customer->id, 'name' => $claim->customer->first_name . ' ' . $claim->customer->last_name] : null,
            'messages'        => $claim->messages->map(fn ($m) => [
                'id'         => $m->id,
                'body'       => $m->body,
                'sender'     => $m->sender_type,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
            'created_at'      => $claim->created_at?->toIso8601String(),
        ]);
    }
}
