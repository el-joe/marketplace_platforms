<?php

namespace App\Http\Requests\ProductRequest;

use App\Models\MarketerAdmin;
use App\Models\ProductRequest;
use App\Models\VendorAdmin;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for a vendor or marketer submitting a new product request.
 * Shared by the partner (vendor guard) and marketer (marketer guard) panels;
 * route middleware handles authentication and authorization.
 */
class StoreProductRequestSubmission extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'uuid', Rule::exists('categories', 'id')->where('is_active', true)],
            'brand_id' => ['nullable', 'uuid', Rule::exists('brands', 'id')],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'gtin' => ['nullable', 'string', 'regex:/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', Rule::unique('products', 'gtin')],
            'model_number' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'variants_note' => ['nullable', 'string', 'max:1000'],
            'reference_url' => ['nullable', 'url:http,https', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'country_id' => ['nullable', 'uuid', Rule::exists('countries', 'id')],
            'images' => ['required', 'array', 'min:1', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gtin.unique' => __('product_requests.messages.gtin_exists'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name_en' => __('product_requests.fields.name_en'),
            'name_ar' => __('product_requests.fields.name_ar'),
            'category_id' => __('product_requests.fields.category'),
            'brand_id' => __('product_requests.fields.brand'),
            'brand_name' => __('product_requests.fields.brand_name'),
            'gtin' => __('product_requests.fields.gtin'),
            'model_number' => __('product_requests.fields.model_number'),
            'description' => __('product_requests.fields.description'),
            'variants_note' => __('product_requests.fields.variants_note'),
            'reference_url' => __('product_requests.fields.reference_url'),
            'notes' => __('product_requests.fields.notes'),
            'country_id' => __('product_requests.fields.country'),
            'images' => __('product_requests.fields.images'),
            'images.*' => __('product_requests.fields.images'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->hasDuplicatePendingRequest()) {
                $validator->errors()->add(
                    $this->filled('gtin') ? 'gtin' : 'name_en',
                    __('product_requests.messages.duplicate_pending'),
                );
            }
        });
    }

    /**
     * The panel user submitting the request (vendor guard first, then marketer guard).
     */
    public function requester(): VendorAdmin|MarketerAdmin|null
    {
        $vendorAdmin = auth('vendor')->user();
        if ($vendorAdmin instanceof VendorAdmin) {
            return $vendorAdmin;
        }

        $marketerAdmin = auth('marketer')->user();

        return $marketerAdmin instanceof MarketerAdmin ? $marketerAdmin : null;
    }

    /**
     * Same owner already has a pending request for the same GTIN — or, without a GTIN,
     * for the same English name.
     */
    private function hasDuplicatePendingRequest(): bool
    {
        $requester = $this->requester();
        if (! $requester) {
            return false;
        }

        return ProductRequest::query()
            ->pending()
            ->when(
                $requester instanceof VendorAdmin,
                fn ($query) => $query->forVendor($requester->vendor_id),
                fn ($query) => $query->forMarketer($requester->marketer_id),
            )
            ->when(
                $this->filled('gtin'),
                fn ($query) => $query->where('gtin', $this->input('gtin')),
                fn ($query) => $query->where('name_en', trim((string) $this->input('name_en'))),
            )
            ->exists();
    }
}
