<?php

namespace App\Http\Requests\Admin;

use App\Models\ExclusiveContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreExclusiveContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classified_category_id' => ['nullable', 'uuid', 'exists:classified_categories,id'],
            'classified_listing_id' => ['nullable', 'uuid', 'exists:classified_listings,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', 'in:pending,active,expired,revoked'],
            'contract_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $listingId = $this->input('classified_listing_id');

            if (! $listingId) {
                return;
            }

            $startsAt = $this->input('starts_at');
            $endsAt = $this->input('ends_at');

            $overlaps = ExclusiveContract::where('classified_listing_id', $listingId)
                ->whereIn('status', ['pending', 'active'])
                ->when($this->route('exclusiveContract'), fn ($q, $current) => $q->whereKeyNot($current->id))
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();

            if ($overlaps) {
                $validator->errors()->add(
                    'classified_listing_id',
                    __('هذا الإعلان لديه عقد حصري نشط آخر يتقاطع مع هذه الفترة.')
                );
            }
        });
    }
}
