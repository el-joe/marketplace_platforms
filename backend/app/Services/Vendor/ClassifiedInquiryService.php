<?php

namespace App\Services\Vendor;

use App\Models\ClassifiedInquiry;
use Illuminate\Validation\ValidationException;

class ClassifiedInquiryService
{
    public function updateStatus(ClassifiedInquiry $inquiry, string $status): ClassifiedInquiry
    {
        if (! in_array($status, ['contacted', 'closed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid status transition.',
            ]);
        }

        $inquiry->update(['status' => $status]);

        return $inquiry->fresh();
    }
}
