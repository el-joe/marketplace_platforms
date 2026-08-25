<?php

namespace App\Http\Controllers\Delivery\Api;

use App\Enums\DeliveryAgentDocumentStatus;
use App\Enums\DeliveryAgentDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\Profile\ReuploadDocumentRequest;
use App\Http\Requests\Delivery\Profile\UpdatePasswordRequest;
use App\Http\Requests\Delivery\Profile\UpdateProfileRequest;
use App\Http\Resources\Delivery\DeliveryAgentProfileResource;
use App\Http\Resources\Delivery\DocumentResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();
        $agent->load(['zone', 'country']);

        return ApiResponse::success(new DeliveryAgentProfileResource($agent));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        $agent->update($request->only(['vehicle_type', 'vehicle_plate']));

        return ApiResponse::success(new DeliveryAgentProfileResource($agent), __('delivery.messages.profile.updated'));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        $agent->update(['password' => Hash::make($request->password)]);

        return ApiResponse::success(null, __('delivery.messages.profile.password_updated'));
    }

    public function documents(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        $docs = $agent->documents()->orderBy('document_type')->get();

        return ApiResponse::success(DocumentResource::collection($docs)->resolve());
    }

    public function reuploadDocument(ReuploadDocumentRequest $request, string $type): JsonResponse
    {
        if (!in_array($type, array_column(DeliveryAgentDocumentType::cases(), 'value'), true)) {
            return ApiResponse::error(__('delivery.messages.profile.invalid_document_type'), [], 422);
        }

        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();

        $path = $request->file('file')->store("delivery/documents/{$agent->id}", 'public');

        DeliveryAgentDocument::updateOrCreate(
            ['agent_id' => $agent->id, 'document_type' => $type],
            [
                'file_path'         => $path,
                'status'            => DeliveryAgentDocumentStatus::Pending,
                'rejection_reason'  => null,
                'verified_at'       => null,
                'verified_by_admin_id' => null,
            ]
        );

        return ApiResponse::success(null, __('delivery.messages.profile.document_submitted'));
    }
}
