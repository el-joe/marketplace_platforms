<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerSellerContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A seller's signed collaboration contract with one marketer (admin view). Requires marketers.view; the contract
 * must belong to the marketer in the URL.
 */
class MarketerCollaborationContractController extends Controller
{
    public function show(Marketer $marketer, MarketerSellerContract $contract): View
    {
        $this->authorizeFor($marketer, $contract);

        $contract->load(['vendor:id,store_name', 'template:id,name,classified_category_id', 'template.category:id,name_en,name_ar', 'country:id,name_en,name_ar', 'campaign:id,title', 'signedBy:id,name']);

        return view('admin.marketers.collaboration-contract', compact('marketer', 'contract'));
    }

    public function file(Marketer $marketer, MarketerSellerContract $contract): StreamedResponse
    {
        $this->authorizeFor($marketer, $contract);
        abort_unless($contract->hasSignedFile() && Storage::disk('private')->exists($contract->signed_file_path), 404);

        return Storage::disk('private')->download($contract->signed_file_path, $contract->signed_file_name, ['Content-Type' => $contract->signed_file_mime ?? 'application/octet-stream']);
    }

    private function authorizeFor(Marketer $marketer, MarketerSellerContract $contract): void
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);
        abort_unless($contract->marketer_id === $marketer->id, 404);
    }
}
