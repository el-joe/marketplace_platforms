<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\ExclusiveContract;
use App\Models\Marketer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExclusiveContractController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    public function index(): View
    {
        $contracts = ExclusiveContract::where('marketer_id', $this->marketer()->id)
            ->with(['classifiedCategory', 'classifiedListing'])->latest()->paginate(20);

        return view('marketer.exclusive-contracts.index', compact('contracts'));
    }

    public function show(string $contract): View
    {
        $contract = ExclusiveContract::where('marketer_id', $this->marketer()->id)
            ->with(['classifiedCategory', 'classifiedListing'])->findOrFail($contract);

        return view('marketer.exclusive-contracts.show', compact('contract'));
    }

    public function download(string $contract): StreamedResponse
    {
        $contract = ExclusiveContract::where('marketer_id', $this->marketer()->id)->findOrFail($contract);
        abort_unless($contract->contract_file_path && Storage::disk('private')->exists($contract->contract_file_path), 404);

        return Storage::disk('private')->download($contract->contract_file_path);
    }
}
