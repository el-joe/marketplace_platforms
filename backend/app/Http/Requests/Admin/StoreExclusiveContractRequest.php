<?php

namespace App\Http\Requests\Admin;

use App\Models\ExclusiveContract;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class StoreExclusiveContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(
            redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', implode(' | ', $validator->errors()->all()))
        );
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
            $categoryId = $this->input('classified_category_id');
            if (! $this->input('starts_at') || ! $this->input('ends_at')) {
                return;
            }

            $startsAt = Carbon::parse($this->input('starts_at'));
            $endsAt = Carbon::parse($this->input('ends_at'));

            if ($listingId) {
                $overlaps = ExclusiveContract::where('classified_listing_id', $listingId)
                    ->whereIn('status', ['pending', 'active'])
                    ->when($this->route('exclusiveContract'), fn ($q, $current) => $q->whereKeyNot($current->id))
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt)
                    ->exists();

                if ($overlaps) {
                    $validator->errors()->add(
                        'classified_listing_id',
                        __('admin.contract_conflict_error')
                    );
                }

                return;
            }

            if ($categoryId) {
                $categoryOverlaps = ExclusiveContract::where('classified_category_id', $categoryId)
                    ->whereNull('classified_listing_id')
                    ->whereIn('status', ['pending', 'active'])
                    ->when($this->route('exclusiveContract'), fn ($q, $current) => $q->whereKeyNot($current->id))
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt)
                    ->exists();

                if ($categoryOverlaps) {
                    $validator->errors()->add(
                        'classified_category_id',
                        __('admin.contract_category_conflict_error')
                    );
                }
            }
        });
    }
}
