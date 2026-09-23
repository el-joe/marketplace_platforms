<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Services\Marketer\MarketerOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function __construct(private MarketerOnboardingService $service) {}

    public function jobType(Request $request): JsonResponse
    {
        if ($blocked = $this->guard()) {
            return $blocked;
        }

        $data = $request->validate([
            'job_keys' => ['required', 'array', 'min:1'],
            'job_keys.*' => ['required', 'string', Rule::in([...$this->service->availableJobKeys(), 'broker'])],
        ]);

        $this->service->setJobTypes($this->marketer(), $data['job_keys']);

        return response()->json(['success' => true, 'data' => ['next_step' => 'profile']]);
    }

    public function profile(Request $request): JsonResponse
    {
        if ($blocked = $this->guard()) {
            return $blocked;
        }
        $marketer = $this->marketer();

        if (! $marketer->marketerJobs()->exists()) {
            return response()->json(['success' => false, 'message' => __('marketer.job_type_first')], 422);
        }

        $data = $request->validate($this->service->profileRules($marketer));

        $this->service->saveProfile($marketer, $data, $request->file('avatar'), $request->file('banner'));
        $this->service->storeDocumentsFromRequest($marketer, $request->file('cv'), $request->file('certifications', []));

        $marketer->refresh();

        return response()->json(['success' => true, 'data' => ['marketer' => [
            'id' => $marketer->id,
            'name' => $marketer->name,
            'onboarding_completed_at' => $marketer->onboarding_completed_at,
        ], 'next_step' => 'complete']]);
    }

    public function complete(): JsonResponse
    {
        if ($blocked = $this->guard()) {
            return $blocked;
        }
        $marketer = $this->marketer();
        $missing = $this->service->complete($marketer);

        if ($missing) {
            return response()->json([
                'success' => false,
                'message' => __('marketer.onboarding_incomplete'),
                'data' => ['missing' => $missing],
            ], 422);
        }

        // Same JWT the client already holds; no new token is issued.
        return response()->json(['success' => true, 'data' => [
            'onboarding_completed_at' => $marketer->onboarding_completed_at,
        ]]);
    }

    private function marketer()
    {
        return Auth::guard('marketer_api')->user()->marketer;
    }

    private function guard(): ?JsonResponse
    {
        $admin = Auth::guard('marketer_api')->user();
        $marketer = $admin->marketer;

        if (! $admin->is_active || in_array($marketer->global_status?->value, ['suspended', 'blacklisted', 'rejected'], true)) {
            return response()->json(['success' => false, 'message' => 'Account is suspended.'], 403);
        }
        if (! $marketer->needsOnboarding()) {
            return response()->json(['success' => false, 'message' => __('marketer.onboarding_already_done')], 409);
        }

        return null;
    }
}
