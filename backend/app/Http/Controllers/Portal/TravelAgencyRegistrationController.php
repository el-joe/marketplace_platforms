<?php

namespace App\Http\Controllers\Portal;

use App\Enums\TravelAgencyStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\TravelAgency;
use App\Models\TravelAgencyMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Public travel-agency registration wizard, reached from the travel-agency
 * landing inside the /companies journey.
 *
 * It mirrors the existing data model exactly: a TravelAgency is created in
 * `pending` status plus its owner TravelAgencyMember (the account that can log
 * in to the travel-agency dashboard, awaiting admin approval). No business
 * fields beyond the travel_agencies / travel_agency_members tables are
 * invented.
 */
class TravelAgencyRegistrationController extends Controller
{
    /** Session key holding the in-progress registration data. */
    private const SESSION_KEY = 'travel_reg_data';

    /** Steps that persist to the session before the final submit. */
    private const STEPS = [1, 2];

    // ── Step validation rule sets ─────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function stepRules(int $step): array
    {
        return match ($step) {
            1 => [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required', 'email', 'max:255',
                    Rule::unique('travel_agencies', 'email'),
                    Rule::unique('travel_agency_members', 'email'),
                ],
                'phone' => ['required', 'string', 'max:30'],
                'country_id' => ['required', 'string', 'exists:countries,id,is_active,1'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            2 => [
                'agency_name' => ['required', 'string', 'max:255'],
                'license_number' => ['nullable', 'string', 'max:100'],
                'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function stepMessages(int $step): array
    {
        if ($step === 1) {
            return [
                'email.unique' => __('portal.travel_agency.email_unique'),
                'email.email' => __('portal.travel_agency.email_invalid'),
                'country_id.exists' => __('portal.travel_agency.country_invalid'),
                'password.min' => __('portal.registration.password_min'),
                'password.confirmed' => __('portal.registration.password_confirmed'),
            ];
        }

        if ($step === 2) {
            return [
                'logo.image' => __('portal.travel_agency.logo_invalid'),
                'logo.mimes' => __('portal.travel_agency.logo_invalid'),
                'logo.max' => __('portal.travel_agency.logo_too_large'),
            ];
        }

        return [];
    }

    // ── Public pages ──────────────────────────────────────────────────────

    public function show(): View
    {
        $countries = Country::where('is_active', true)->orderBy('name_ar')->get();
        $currentStep = session(self::SESSION_KEY.'.current_step', 1);
        $savedData = session(self::SESSION_KEY, []);

        return view('portal.travel-agency-register', compact('countries', 'currentStep', 'savedData'));
    }

    public function success(): View
    {
        return view('portal.travel-agency-register-success');
    }

    // ── AJAX step save ────────────────────────────────────────────────────

    public function storeStep(Request $request, int $step): JsonResponse
    {
        abort_unless(in_array($step, self::STEPS, true), 404);

        $validator = Validator::make(
            $request->all(),
            $this->stepRules($step),
            $this->stepMessages($step)
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($step === 2) {
            $data = $this->stageLogo($request, $data);
        }

        session([self::SESSION_KEY.".step{$step}" => $data]);
        session([self::SESSION_KEY.'.current_step' => $step + 1]);

        return response()->json([
            'success' => true,
            'next_step' => $step + 1,
        ]);
    }

    /**
     * Store an uploaded logo on the public disk and remember its temp path in
     * the step payload, discarding any previously staged file.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stageLogo(Request $request, array $data): array
    {
        unset($data['logo']);

        if (! $request->hasFile('logo')) {
            return $data;
        }

        $existing = session(self::SESSION_KEY.'.step2.logo_path');
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $path = 'travel-agency-logos/temp/'.Str::uuid().'.'.$ext;

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $data['logo_path'] = $path;
        $data['logo_filename'] = $file->getClientOriginalName();

        return $data;
    }

    public function removeLogo(): JsonResponse
    {
        $path = session(self::SESSION_KEY.'.step2.logo_path');

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        session()->forget([
            self::SESSION_KEY.'.step2.logo_path',
            self::SESSION_KEY.'.step2.logo_filename',
        ]);

        return response()->json(['success' => true]);
    }

    // ── Final submission ──────────────────────────────────────────────────

    public function complete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'terms_agreed' => ['required', 'accepted'],
            'privacy_agreed' => ['required', 'accepted'],
        ], [
            'terms_agreed.accepted' => __('portal.registration.terms_agreed_accepted'),
            'privacy_agreed.accepted' => __('portal.registration.privacy_agreed_accepted'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $step1 = session(self::SESSION_KEY.'.step1', []);
        $step2 = session(self::SESSION_KEY.'.step2', []);

        if (empty($step1['email']) || empty($step2['agency_name'])) {
            return response()->json([
                'success' => false,
                'message' => __('portal.travel_agency.incomplete_data'),
            ], 422);
        }

        // The step 1 uniqueness check may be stale by the time the visitor
        // submits; re-check so we never violate the email unique constraints.
        if ($this->emailTaken($step1['email'])) {
            return response()->json([
                'success' => false,
                'errors' => ['email' => [__('portal.travel_agency.email_unique')]],
            ], 422);
        }

        $country = Country::where('id', $step1['country_id'] ?? null)
            ->where('is_active', true)
            ->first();

        if (! $country) {
            return response()->json([
                'success' => false,
                'message' => __('portal.registration.invalid_country'),
            ], 422);
        }

        $logoPath = $step2['logo_path'] ?? null;

        DB::transaction(function () use ($step1, $step2, $country, $logoPath): void {
            $agency = TravelAgency::create([
                'name' => $step2['agency_name'],
                'email' => $step1['email'],
                'phone' => $step1['phone'],
                'password' => $step1['password'],
                'license_number' => $step2['license_number'] ?? null,
                'country_id' => $country->id,
                'status' => TravelAgencyStatus::Pending,
            ]);

            $member = TravelAgencyMember::create([
                'travel_agency_id' => $agency->id,
                'name' => $step1['name'],
                'email' => $step1['email'],
                'phone' => $step1['phone'],
                'password' => $step1['password'],
                'role' => 'travel_agency_owner',
                'is_owner' => true,
                'is_active' => true,
            ]);

            $ownerRole = Role::where('name', 'travel_agency_owner')
                ->where('guard_name', 'travel_agency')
                ->first();

            if ($ownerRole) {
                $member->assignRole($ownerRole);
            }

            if ($logoPath) {
                $this->moveLogo($logoPath, $agency);
            }
        });

        session()->forget(self::SESSION_KEY);

        return response()->json([
            'success' => true,
            'redirect' => route('portal.companies.travel-agency.register.success'),
        ]);
    }

    private function moveLogo(string $tempPath, TravelAgency $agency): void
    {
        if (! Storage::disk('public')->exists($tempPath)) {
            return;
        }

        $ext = strtolower(pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'png');
        $permPath = "travel-agency-logos/{$agency->id}/logo.{$ext}";

        Storage::disk('public')->move($tempPath, $permPath);
        $agency->update(['logo_path' => $permPath]);
    }

    private function emailTaken(string $email): bool
    {
        return TravelAgency::where('email', $email)->exists()
            || TravelAgencyMember::where('email', $email)->exists();
    }
}
