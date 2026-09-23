<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Enums\ClassifiedInquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassifiedInquiry;
use App\Models\Marketer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ClassifiedInquiryController extends Controller
{
    private function query()
    {
        $marketer = Auth::guard('marketer_api')->user()->marketer;

        return ClassifiedInquiry::query()->whereHas('listing', fn ($q) => $q->withTrashed()
            ->where('seller_type', Marketer::class)->where('seller_id', $marketer->id))
            ->with(['listing' => fn ($q) => $q->withTrashed(), 'customer']);
    }

    private function present(ClassifiedInquiry $i): array
    {
        return [
            'id' => $i->id, 'message' => $i->message, 'contact_phone' => $i->contact_phone,
            'status' => $i->status->value,
            'listing' => $i->listing ? ['id' => $i->listing->id, 'title_ar' => $i->listing->title_ar] : null,
            'customer' => ['id' => $i->customer_id, 'name' => $i->customer?->name],
            'created_at' => $i->created_at,
        ];
    }

    public function index(): JsonResponse
    {
        $page = $this->query()->latest()->paginate(20);
        $page->getCollection()->transform(fn ($i) => $this->present($i));

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function show(string $id): JsonResponse
    {
        $i = $this->query()->findOrFail($id);
        if ($i->status === ClassifiedInquiryStatus::New) {
            $i->update(['status' => ClassifiedInquiryStatus::Contacted]);
        }

        return response()->json(['success' => true, 'data' => $this->present($i)]);
    }

    public function close(string $id): JsonResponse
    {
        $i = $this->query()->findOrFail($id);
        $i->update(['status' => ClassifiedInquiryStatus::Closed]);

        return response()->json(['success' => true, 'data' => $this->present($i)]);
    }
}
