<?php

namespace App\Http\Requests\Api\TravelAgencyPortal\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $agencyId = Auth::guard('travel_agencies')->id();

        $rules = [
            'travel_package_id' => [
                'required',
                'uuid',
                Rule::exists('travel_packages', 'id')->where('travel_agency_id', $agencyId),
            ],
            'travelers_count' => ['required', 'integer', 'min:1'],
            'customer_mode' => ['required', 'in:existing,new'],
            'from_inquiry' => ['nullable', 'uuid', 'exists:travel_package_inquiries,id'],
        ];

        if ($this->input('customer_mode') === 'existing') {
            $rules['customer_id'] = ['required', 'uuid', 'exists:customers,id'];
        } else {
            $rules['new_name'] = ['required', 'string', 'max:255'];
            $rules['new_phone'] = ['required', 'string', 'max:30'];
            $rules['new_email'] = ['required', 'email', 'max:255', 'unique:customers,email'];
        }

        return $rules;
    }
}
