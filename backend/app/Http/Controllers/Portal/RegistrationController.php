<?php

namespace App\Http\Controllers\Portal;

use App\Enums\RegistrationFlow;
use App\Enums\VendorDocumentRequirementLevel;
use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\KeepsWizardPasswordHashed;
use App\Mail\NewVendorApplicationAdminMail;
use App\Mail\VendorApplicationReceivedMail;
use App\Models\Address;
use App\Models\Admin;
use App\Models\City;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorContract;
use App\Models\VendorDocument;
use App\Models\VendorDocumentType;
use App\Notifications\Admin\NewVendorApplicationSubmitted;
use App\Services\ActivityLoggerService;
use App\Services\Portal\JoinService;
use App\Services\PortalAnalytics\PortalTracker;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ContractResolver;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    use KeepsWizardPasswordHashed;

    public function __construct(
        private readonly SellerAgreementService $agreements,
        private readonly CategoryContractService $contracts,
        private readonly ContractResolver $resolver,
        private readonly PortalTracker $tracker,
        private readonly JoinService $joinService,
    ) {}

    // ── Step validation rule sets ─────────────────────────────────────────

    private function stepRules(int $step): array
    {
        return match ($step) {
            1 => [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:vendors,email|unique:vendor_admins,email',
                'phone' => 'required|string|max:30|unique:vendors,phone',
                'gender' => 'required|in:male,female',
                'country_id' => ['required', Country::availableForVendorsRule()],
            ] + $this->wizardPasswordRules(session('reg_data.step1', [])),
            2 => [
                'store_name' => 'required|string|max:255|unique:vendors,store_name',
                'store_slug' => 'required|string|max:120|alpha_dash|unique:vendors,store_slug',
                'business_type' => 'required|in:individual,sole_prop,llc,corp',
                'business_name' => 'required|string|max:255',
                'business_registration_number' => 'nullable|string|max:100',
                'tax_id' => 'nullable|string|max:100',
                'store_description' => 'nullable|string|max:1000',
            ],
            3 => [
                'contact_email' => 'required|email|max:255',
                'contact_phone' => 'required|string|max:30',
                'whatsapp_number' => 'nullable|string|max:30',
                'city_id' => 'required|exists:cities,id',
                'area' => 'nullable|string|max:255',
                'street_address' => 'required|string|max:500',
                'building' => 'nullable|string|max:100',
                'floor' => 'nullable|string|max:20',
                'apartment' => 'nullable|string|max:20',
                'postal_code' => 'nullable|string|max:20',
            ],
            default => [],
        };
    }

    private function stepMessages(int $step): array
    {
        if ($step === 1) {
            return [
                'email.unique' => __('portal.registration.email_unique'),
                'phone.unique' => __('portal.registration.phone_unique'),
                'password.min' => __('portal.registration.password_min'),
                'password.confirmed' => __('portal.registration.password_confirmed'),
            ];
        }
        if ($step === 2) {
            return [
                'store_name.unique' => __('portal.registration.store_name_unique'),
                'store_slug.unique' => __('portal.registration.store_slug_unique'),
                'store_slug.alpha_dash' => __('portal.registration.store_slug_alpha_dash'),
            ];
        }

        return [];
    }

    // ── Public pages ──────────────────────────────────────────────────────

    /**
     * GET /register — show multi-step wizard
     */
    public function show(Request $request): View
    {
        // The account type is decided by the entry point: the Open Souq landing links here with
        // ?account=classified and creates a classified-ads vendor; every other link creates a product vendor.
        $vendorType = $request->query('account') === 'classified' ? VendorType::ClassifiedVendor : VendorType::ProductVendor;
        session(['reg_data.vendor_type' => $vendorType->value]);
        $isClassifiedAccount = $vendorType === VendorType::ClassifiedVendor;

        // A Classifieds section landing passes its category (?category=slug): the wizard then shows and collects
        // that section's contract too. Anything else (no category, unknown, inactive, sub-category) clears it.
        $classifiedCategory = $isClassifiedAccount && is_string($request->query('category'))
            ? $this->joinService->findJoinableClassifiedCategory($request->query('category'), null)
            : null;
        session(['reg_data.classified_category_id' => $classifiedCategory?->id]);

        $countries = Country::availableForVendors()->orderBy('name_ar')->get();
        $currentStep = session('reg_data.current_step', 1);
        $savedData = $this->wizardDataForView(session('reg_data', []));

        // Carry the portal country chosen earlier into the country field, unless
        // the applicant already saved a different one.
        $portalCountryId = $countries->firstWhere('site_code', Country::resolveSiteCode(null))?->id;
        if ($portalCountryId && empty($savedData['step1']['country_id'])) {
            $savedData['step1']['country_id'] = $portalCountryId;
        }

        $this->tracker->stepReached(RegistrationFlow::Vendor, max(1, (int) $currentStep));

        // The Seller Agreement depends on the seller's country (chosen in step 1), so every country's agreement is
        // prepared here and step 5 shows the one for the selected country. Countries without one are mapped to null.
        $agreementByCountry = [];
        $agreementDocs = [];

        foreach ($countries as $country) {
            $template = $this->agreements->latestPublished($country->id);
            $agreementByCountry[$country->id] = $template?->id;

            if ($template && ! isset($agreementDocs[$template->id])) {
                $agreementDocs[$template->id] = ['template' => $template, 'doc' => $this->contracts->presentPublic($template)];
            }
        }

        // The chosen section's own contract, per country (same resolution as when listing in it: the category's
        // country assignment, its parents', then the country default). Null where none applies or none is configured.
        $categoryContractByCountry = [];
        $categoryContractDocs = [];

        if ($classifiedCategory) {
            foreach ($countries as $country) {
                $template = $this->contracts->templateFor($classifiedCategory, $country);
                $categoryContractByCountry[$country->id] = $template?->id;

                if ($template && ! isset($categoryContractDocs[$template->id])) {
                    $categoryContractDocs[$template->id] = ['template' => $template, 'doc' => $this->contracts->presentPublic($template)];
                }
            }
        }

        // Registration is never blocked for a country that has no Seller Agreement; its agreement step is skipped.
        $agreementEnforced = false;
        $agreementNotConfiguredMessage = __('contracts.api.not_configured', ['country' => ':country']);

        return view('portal.register', compact('countries', 'currentStep', 'savedData', 'agreementByCountry', 'agreementDocs', 'agreementEnforced', 'agreementNotConfiguredMessage', 'isClassifiedAccount', 'classifiedCategory', 'categoryContractByCountry', 'categoryContractDocs'));
    }

    /**
     * GET /register/success
     */
    public function success(): View
    {
        return view('portal.register-success');
    }

    // ── AJAX step save ────────────────────────────────────────────────────

    /**
     * POST /register/step/{step}
     */
    public function storeStep(Request $request, int $step): JsonResponse
    {
        abort_unless(in_array($step, [1, 2, 3], true), 404);

        $validator = Validator::make(
            $request->all(),
            $this->stepRules($step),
            $this->stepMessages($step)
        );

        if ($validator->fails()) {
            $this->tracker->stepFailed(RegistrationFlow::Vendor, $step);

            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        if ($step === 1) {
            $data = $this->hashWizardPassword($data, session('reg_data.step1', []));
        }

        session(["reg_data.step{$step}" => $data]);
        session(['reg_data.current_step' => $step + 1]);
        $this->tracker->stepCompleted(RegistrationFlow::Vendor, $step);

        return response()->json([
            'success' => true,
            'next_step' => $step + 1,
        ]);
    }

    // ── Document upload ───────────────────────────────────────────────────

    /**
     * GET /register/document-requirements?country_id={id}
     */
    public function documentRequirements(Request $request): JsonResponse
    {
        $request->validate(['country_id' => 'required|exists:countries,id']);

        $country = Country::findOrFail($request->input('country_id'));

        $docs = $country->requiredDocumentTypesFor()->map(fn ($item) => [
            'type_id' => $item['type']->id,
            'code' => $item['type']->code,
            'name_en' => $item['type']->name_en,
            'name_ar' => $item['type']->name_ar,
            'description_en' => $item['type']->description_en ?? null,
            'description_ar' => $item['type']->description_ar ?? null,
            'requirement_level' => $item['requirement_level'],
            'accepted_file_types' => $item['type']->accepted_file_types ?? ['pdf', 'jpg', 'jpeg', 'png'],
            'max_file_size_kb' => $item['type']->max_file_size_kb ?? 5120,
            'requires_expiry_date' => (bool) $item['type']->requires_expiry_date,
        ]);

        return response()->json(['success' => true, 'documents' => $docs]);
    }

    /**
     * POST /register/upload
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        // Validate document type first so we can look up per-type constraints
        $typeValidator = Validator::make($request->all(), [
            'document_type' => 'required|string|exists:vendor_document_types,code,is_active,1',
        ]);

        if ($typeValidator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $typeValidator->errors(),
            ], 422);
        }

        $docType = VendorDocumentType::where('code', $request->input('document_type'))
            ->where('is_active', true)
            ->first();

        $acceptedMimes = implode(',', $docType->accepted_file_types ?? ['pdf', 'jpg', 'jpeg', 'png']);
        $maxKb = $docType->max_file_size_kb ?? 5120;

        $validator = Validator::make($request->all(), [
            'file' => "required|file|mimes:{$acceptedMimes}|max:{$maxKb}",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        $type = $request->input('document_type');
        $ext = $file->getClientOriginalExtension();
        $uuid = (string) Str::uuid();
        $path = "vendor-docs/temp/{$uuid}.{$ext}";

        // Delete previous temp for this type if any
        $existing = session("reg_data.documents.{$type}");
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        session(["reg_data.documents.{$type}" => $path]);

        if (RegistrationFlow::Vendor->sessionReached(4)) {
            $this->tracker->stepReached(RegistrationFlow::Vendor, 4);
        }

        return response()->json([
            'success' => true,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'type' => $type,
            'is_image' => in_array(strtolower($ext), ['jpg', 'jpeg', 'png']),
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * DELETE /register/document
     */
    public function removeDocument(Request $request): JsonResponse
    {
        $type = $request->input('document_type');
        abort_unless(
            VendorDocumentType::where('code', $type)->where('is_active', true)->exists(),
            422
        );

        $path = session("reg_data.documents.{$type}");
        if ($path) {
            Storage::disk('public')->delete($path);
            session()->forget("reg_data.documents.{$type}");
        }

        return response()->json(['success' => true]);
    }

    // ── Final submission ──────────────────────────────────────────────────

    /**
     * The Classifieds section this applicant chose on /join (carried into the wizard as ?category=), re-read from
     * the database so a section deactivated meanwhile is ignored. Only a classified-ads account has one.
     */
    private function registeringClassifiedCategory(): ?ClassifiedCategory
    {
        $categoryId = session('reg_data.classified_category_id');

        if (! is_string($categoryId) || session('reg_data.vendor_type') !== VendorType::ClassifiedVendor->value) {
            return null;
        }

        return $this->joinService->findJoinableClassifiedCategory($categoryId, null);
    }

    /**
     * POST /register/complete
     */
    public function complete(Request $request): JsonResponse
    {
        // The agreement is the one of the country chosen in step 1, decided here and never taken from the client.
        $country = ($countryId = session('reg_data.step1.country_id')) ? Country::find($countryId) : null;
        $agreement = $this->agreements->latestPublished($country?->id);

        // A country without a published Seller Agreement does not block registration: the agreement step is
        // simply skipped for it (no signature is collected or required).

        $rules = [
            'terms_agreed' => 'required|accepted',
            'privacy_agreed' => 'required|accepted',
        ];

        // The Classifieds section chosen on /join brings its own contract, decided here for the seller's country.
        $classifiedCategory = $this->registeringClassifiedCategory();
        $categoryContract = $classifiedCategory ? $this->contracts->templateFor($classifiedCategory, $country) : null;

        if ($agreement || $categoryContract) {
            $rules['signature_name'] = 'required|string|min:3|max:150';
            $rules['agreement_language'] = 'required|in:ar,en';
        }

        if ($agreement) {
            $rules['agreement_template_id'] = ['required', 'in:'.$agreement->id];
        }

        if ($categoryContract) {
            $rules['category_contract_template_id'] = ['required', 'in:'.$categoryContract->id];
            $rules['category_contract_agreed'] = 'required|accepted';
        }

        $validator = Validator::make($request->all(), $rules, [
            'terms_agreed.accepted' => __('portal.registration.terms_agreed_accepted'),
            'privacy_agreed.accepted' => __('portal.registration.privacy_agreed_accepted'),
            'signature_name.required' => __('portal.registration.signature_name_required'),
            'signature_name.min' => __('portal.registration.signature_name_required'),
            'agreement_template_id.in' => __('portal.registration.agreement_changed'),
            'category_contract_template_id.required' => __('portal.registration.category_contract_changed'),
            'category_contract_template_id.in' => __('portal.registration.category_contract_changed'),
            'category_contract_agreed.required' => __('portal.registration.category_contract_required'),
            'category_contract_agreed.accepted' => __('portal.registration.category_contract_required'),
        ]);

        if ($validator->fails()) {
            $this->tracker->stepFailed(RegistrationFlow::Vendor, 5);

            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $step1 = session('reg_data.step1', []);
        $step2 = session('reg_data.step2', []);
        $step3 = session('reg_data.step3', []);
        $docs = session('reg_data.documents', []);
        $vendorType = VendorType::tryFrom((string) session('reg_data.vendor_type')) ?? VendorType::ProductVendor;
        $docExpiries = $request->input('doc_expiries', []);

        // Guard: all steps must be present
        $passwordHash = $this->wizardPasswordHash($step1);

        if (empty($step1['email']) || $passwordHash === null || empty($step2['store_name']) || empty($step3['street_address'])) {
            $this->tracker->stepFailed(RegistrationFlow::Vendor, 5);

            return response()->json([
                'success' => false,
                'message' => __('portal.registration.incomplete_data'),
            ], 422);
        }

        // Validate required documents against country's document requirements
        $country = Country::availableForVendors()->find($step1['country_id'] ?? null);
        if (! $country) {
            $this->tracker->stepFailed(RegistrationFlow::Vendor, 5);

            return response()->json([
                'success' => false,
                'message' => __('portal.registration.invalid_country'),
            ], 422);
        }

        $requiredDocs = $country->requiredDocumentTypesFor();
        $mandatoryMissing = [];

        foreach ($requiredDocs as $item) {
            if ($item['requirement_level'] === VendorDocumentRequirementLevel::Mandatory && empty($docs[$item['type']->code])) {
                $mandatoryMissing[] = $item['type']->name_ar;
            }
        }

        if (! empty($mandatoryMissing)) {
            $this->tracker->stepFailed(RegistrationFlow::Vendor, 5);

            return response()->json([
                'success' => false,
                'errors' => [
                    'documents' => [__('portal.registration.documents_required', ['documents' => implode(', ', $mandatoryMissing)])],
                ],
            ], 422);
        }

        // Build the set of acceptable doc codes for this country (mandatory + optional)
        $acceptableCodes = $requiredDocs->pluck('type.code')->flip()->all();

        // Steps 1–2 were validated when entered, but another account may have taken the same
        // email, phone, store name or slug since then: re-check before writing anything.
        if ($conflict = $this->uniquenessConflict($step1, $step2)) {
            $this->tracker->stepFailed(RegistrationFlow::Vendor, 5);

            return $conflict;
        }

        $signedContract = null;
        $confirmationEmailFailed = false;
        $createdVendor = null;

        try {
            DB::transaction(function () use ($request, $passwordHash, $agreement, $classifiedCategory, $categoryContract, &$signedContract, &$confirmationEmailFailed, &$createdVendor, $step1, $step2, $step3, $docs, $docExpiries, $acceptableCodes, $vendorType) {
                // ① Create Vendor
                $vendor = Vendor::create([
                    'name' => $step1['name'],
                    'email' => $step1['email'],
                    'phone' => $step1['phone'],
                    'gender' => $step1['gender'],
                    'password' => $passwordHash,
                    'country_id' => $step1['country_id'],
                    'store_name' => $step2['store_name'],
                    'store_slug' => $step2['store_slug'],
                    'store_description' => $step2['store_description'] ?? null,
                    'business_type' => $step2['business_type'],
                    'business_name' => $step2['business_name'],
                    'business_registration_number' => $step2['business_registration_number'] ?? null,
                    'tax_id' => $step2['tax_id'] ?? null,
                    'contact_email' => $step3['contact_email'],
                    'contact_phone' => $step3['contact_phone'],
                    'whatsapp_number' => $step3['whatsapp_number'] ?? null,
                    'global_status' => 'pending',
                    'vendor_type' => $vendorType,
                    // 'approved_at' => 0,
                ]);
                $createdVendor = $vendor;

                // ② Create billing address (polymorphic)
                $address = Address::create([
                    'addressable_type' => Vendor::class,
                    'addressable_id' => $vendor->id,
                    'address_type' => 'billing',
                    'country_id' => $step1['country_id'],
                    'city_id' => $step3['city_id'],
                    'area' => $step3['area'] ?? null,
                    'street_address' => $step3['street_address'],
                    'building' => $step3['building'] ?? null,
                    'floor' => $step3['floor'] ?? null,
                    'apartment' => $step3['apartment'] ?? null,
                    'postal_code' => $step3['postal_code'] ?? null,
                    'recipient_name' => $step1['name'],
                    'recipient_phone' => $step3['contact_phone'],
                    'is_default' => true,
                ]);

                // ③ Link address back to vendor
                $vendor->update(['business_address_id' => $address->id]);

                // ④ Create VendorAdmin (owner)
                $owner = VendorAdmin::create([
                    'vendor_id' => $vendor->id,
                    'name' => $step1['name'],
                    'email' => $step1['email'],
                    'password' => $passwordHash,
                    'role' => 'owner',
                    'is_active' => 1,
                ]);

                // ④b Sign the Seller Agreement: typed name + agreement, frozen with IP and time
                if ($agreement) {
                    $signedContract = $this->agreements->sign(
                        vendor: $vendor,
                        signer: $owner,
                        template: $agreement,
                        language: (string) $request->input('agreement_language'),
                        signerName: (string) $request->input('signature_name'),
                        ipAddress: $request->ip(),
                        userAgent: $request->userAgent() ?? '',
                        method: 'registration',
                    );
                }

                // ④c Sign the chosen Classifieds section's contract with the same typed name. The service resolves the
                // template again from the new vendor's country, so it is always the one shown in step 5.
                if ($classifiedCategory && $categoryContract) {
                    $this->contracts->sign(
                        vendor: $vendor,
                        signer: $owner,
                        scope: CategoryContractService::SCOPE_CLASSIFIED,
                        categoryId: $classifiedCategory->id,
                        language: (string) $request->input('agreement_language'),
                        signerName: (string) $request->input('signature_name'),
                        ipAddress: $request->ip() ?? '',
                        userAgent: $request->userAgent() ?? '',
                    );
                }

                // ⑤ Move temp documents → permanent, create VendorDocument records
                // Only process docs that are applicable for this country (skip not_applicable)
                $submittedCodes = array_intersect_key($docs, $acceptableCodes);

                $docTypeMap = VendorDocumentType::whereIn('code', array_keys($submittedCodes))
                    ->pluck('id', 'code');

                $docTypeDetails = VendorDocumentType::whereIn('code', array_keys($submittedCodes))
                    ->get()->keyBy('code');

                foreach ($submittedCodes as $docCode => $tempPath) {
                    $ext = pathinfo($tempPath, PATHINFO_EXTENSION);
                    $permPath = "vendor-docs/{$vendor->id}/{$docCode}.{$ext}";

                    if (Storage::disk('public')->exists($tempPath)) {
                        Storage::disk('public')->move($tempPath, $permPath);
                    }

                    $typeRecord = $docTypeDetails[$docCode] ?? null;
                    $expiresAt = null;
                    if ($typeRecord?->requires_expiry_date && ! empty($docExpiries[$docCode])) {
                        $expiresAt = $docExpiries[$docCode];
                    }

                    VendorDocument::create([
                        'vendor_id' => $vendor->id,
                        'vendor_document_type_id' => $docTypeMap[$docCode],
                        'file_path' => $permPath,
                        'status' => 'pending',
                        'expires_at' => $expiresAt,
                    ]);
                }

                // ⑥ Confirmation email: the application is already saved, so a mail failure must not
                // lose it, but it is logged with context and shown to the applicant on the success page.
                try {
                    Mail::to($vendor->email)->send(new VendorApplicationReceivedMail($vendor));
                } catch (\Throwable $e) {
                    $confirmationEmailFailed = true;
                    Log::error('Credential email failed', [
                        'flow' => 'vendor_application_received',
                        'recipient' => $vendor->email,
                        'mailer' => config('mail.default'),
                        'exception' => $e::class,
                        'error' => $e->getMessage(),
                    ]);
                }

                // ⑧ Notify admins of new application (in-app + email)
                try {
                    $admins = Admin::permission('vendors.approve')->get();

                    Notification::send($admins, new NewVendorApplicationSubmitted($vendor));

                    foreach ($admins as $admin) {
                        Mail::to($admin->email)->queue(new NewVendorApplicationAdminMail($vendor));
                    }
                } catch (\Throwable $e) {
                    Log::error('Admin vendor application notification/email failed', [
                        'flow' => 'vendor_application_admin_notice',
                        'vendor_id' => $vendor->id,
                        'exception' => $e::class,
                        'error' => $e->getMessage(),
                    ]);
                }

                // ⑦ Activity log (non-blocking)
                try {
                    app(ActivityLoggerService::class)->log(
                        'New vendor registration submitted',
                        $vendor,
                        null,
                        ['email' => $vendor->email, 'store' => $vendor->store_name],
                        'vendor',
                        'created'
                    );
                } catch (\Throwable $e) {
                    Log::warning('Activity log failed after vendor registration: '.$e->getMessage());
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent registration won the race for a unique column. Never echo the exception:
            // its message carries the SQL, the submitted values and the password hash.
            $field = $this->fieldForUniqueViolation($e);
            Log::warning('Vendor registration hit a unique constraint', ['field' => $field]);
            $this->tracker->stepFailed(RegistrationFlow::Vendor, 5);

            return $this->conflictResponse($field ? [$field => [$this->uniqueMessage($field)]] : []);
        }

        session()->forget('reg_data');

        if ($createdVendor instanceof Vendor) {
            $this->tracker->registrationCompleted(RegistrationFlow::Vendor, $createdVendor);
        }

        if ($confirmationEmailFailed) {
            session()->flash('registration_email_failed', true);
        }

        if ($signedContract instanceof VendorContract) {
            $this->agreements->sendSignedCopy($signedContract);
        }

        return response()->json([
            'success' => true,
            'redirect' => route('portal.register.success'),
        ]);
    }

    // ── Uniqueness guards ─────────────────────────────────────────────────

    /**
     * Re-validates the unique fields saved in the session, returning a 422 response on conflict.
     *
     * @param  array<string, mixed>  $step1
     * @param  array<string, mixed>  $step2
     */
    private function uniquenessConflict(array $step1, array $step2): ?JsonResponse
    {
        $rules = array_intersect_key(
            $this->stepRules(1) + $this->stepRules(2),
            array_flip(['email', 'phone', 'store_name', 'store_slug'])
        );

        $validator = Validator::make($step1 + $step2, $rules, $this->stepMessages(1) + $this->stepMessages(2));

        return $validator->fails() ? $this->conflictResponse($validator->errors()->toArray()) : null;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function conflictResponse(array $errors): JsonResponse
    {
        $storeFields = ['store_name', 'store_slug'];
        $step = array_intersect(array_keys($errors), $storeFields) && ! array_diff(array_keys($errors), $storeFields) ? 2 : 1;
        session(['reg_data.current_step' => $step]);

        return response()->json([
            'success' => false,
            'message' => __('portal.registration.account_conflict'),
            'errors' => $errors,
            'step' => $step,
        ], 422);
    }

    private function fieldForUniqueViolation(UniqueConstraintViolationException $e): ?string
    {
        $detail = (string) ($e->errorInfo[2] ?? '');

        foreach (['vendors_phone_unique' => 'phone', 'vendors_email_unique' => 'email', 'vendor_admins_email_unique' => 'email', 'vendors_store_name_unique' => 'store_name', 'vendors_store_slug_unique' => 'store_slug'] as $index => $field) {
            if (str_contains($detail, $index)) {
                return $field;
            }
        }

        return null;
    }

    private function uniqueMessage(string $field): string
    {
        return ($this->stepMessages(1) + $this->stepMessages(2))[$field.'.unique'];
    }

    // ── AJAX helpers ──────────────────────────────────────────────────────

    /**
     * GET /register/cities?country_id=X
     */
    public function cities(Request $request): JsonResponse
    {
        $request->validate(['country_id' => 'required|exists:countries,id']);

        $cities = City::where('country_id', $request->input('country_id'))
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en']);

        return response()->json(['success' => true, 'cities' => $cities]);
    }

    /**
     * GET /register/check-slug?slug=X
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $slug = Str::slug($request->input('slug', ''));
        $taken = Vendor::where('store_slug', $slug)->exists();

        return response()->json(['available' => ! $taken, 'slug' => $slug]);
    }
}
