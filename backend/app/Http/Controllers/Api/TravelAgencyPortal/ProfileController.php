<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TravelAgencyPortal\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\TravelAgencyPortal\TravelAgencyProfileResource;
use App\Http\Responses\ApiResponse;
use App\Models\TravelAgency;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    private function agency(): TravelAgency
    {
        /** @var TravelAgency $agency */
        $agency = Auth::guard('travel_agencies')->user();

        return $agency;
    }

    public function show(): JsonResponse
    {
        $agency = $this->agency();
        $agency->load('country');

        return ApiResponse::success(new TravelAgencyProfileResource($agency));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $agency = $this->agency();
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($agency->logo_path) {
                Storage::disk('public')->delete($agency->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('travel-agency-logos', 'public');
        }

        unset($data['logo']);

        $agency->update($data);
        $agency->load('country');

        return ApiResponse::success(new TravelAgencyProfileResource($agency), 'Profile updated.');
    }
}
