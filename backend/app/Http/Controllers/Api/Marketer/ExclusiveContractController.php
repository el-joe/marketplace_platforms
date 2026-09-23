<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\ExclusiveContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ExclusiveContractController extends Controller
{
    private function query()
    {
        return ExclusiveContract::where('marketer_id', Auth::guard('marketer_api')->user()->marketer->id)
            ->with(['classifiedCategory', 'classifiedListing']);
    }

    private function fileUrl(ExclusiveContract $c): ?string
    {
        if (! $c->contract_file_path) {
            return null;
        }
        try {
            return Storage::disk('private')->temporaryUrl($c->contract_file_path, now()->addHour());
        } catch (\Throwable) {
            return null; // driver without temporary URL support
        }
    }

    private function present(ExclusiveContract $c): array
    {
        return [
            'id' => $c->id,
            'scope' => $c->classified_listing_id ? 'listing' : 'category',
            'category_name' => $c->classifiedCategory?->name_ar,
            'listing_title' => $c->classifiedListing?->title_ar,
            'starts_at' => $c->starts_at, 'ends_at' => $c->ends_at, 'status' => $c->status,
            'has_file' => (bool) $c->contract_file_path,
            'contract_file_url' => $this->fileUrl($c),
        ];
    }

    public function index(): JsonResponse
    {
        $page = $this->query()->orderByDesc('ends_at')->paginate(20);
        $page->getCollection()->transform(fn ($c) => $this->present($c));

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->present($this->query()->findOrFail($id))]);
    }
}
