<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerContract;
use App\Models\MarketerContractAcceptance;
use App\Models\MarketerContractVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarketerContractController extends Controller
{
    public function show(Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $contract = MarketerContract::with(['versions' => fn ($q) => $q->latest('version_number')])
            ->firstOrCreate(['marketer_id' => $marketer->id]);

        return view('admin.marketers.contract', compact('marketer', 'contract'));
    }

    public function upload(Request $request, Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $validated = $request->validate([
            'content_type' => ['required', 'in:pdf,text'],
            'contract_file' => ['required_if:content_type,pdf', 'file', 'mimes:pdf', 'max:10240'],
            'text_content' => ['required_if:content_type,text', 'string'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
        ]);

        $contract = MarketerContract::firstOrCreate(
            ['marketer_id' => $marketer->id],
            ['current_version' => 0, 'is_required' => true]
        );

        $contract->versions()->where('is_active', true)->update(['is_active' => false]);

        $nextVersion = $contract->current_version + 1;

        $fileUrl = null;
        if ($request->hasFile('contract_file')) {
            $fileUrl = $request->file('contract_file')
                ->storeAs('marketer-contracts', "{$marketer->id}_v{$nextVersion}.pdf", 'local');
        }

        MarketerContractVersion::create([
            'marketer_contract_id' => $contract->id,
            'version_number' => $nextVersion,
            'content_type' => $validated['content_type'],
            'file_url' => $fileUrl,
            'text_content' => $validated['text_content'] ?? null,
            'title_en' => $validated['title_en'] ?? null,
            'title_ar' => $validated['title_ar'] ?? null,
            'is_active' => true,
            'uploaded_by_admin_id' => auth('admin')->id(),
        ]);

        $contract->update(['current_version' => $nextVersion]);

        return back()->with('success', "Contract updated to version {$nextVersion}.");
    }

    public function acceptances(Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $contract = MarketerContract::where('marketer_id', $marketer->id)->firstOrFail();

        $acceptances = MarketerContractAcceptance::with(['contractVersion', 'customer', 'order'])
            ->whereHas('contractVersion', fn ($q) => $q->where('marketer_contract_id', $contract->id))
            ->latest('accepted_at')
            ->paginate(30);

        return view('admin.marketers.contract-acceptances', compact('marketer', 'contract', 'acceptances'));
    }

    public function download(Marketer $marketer, MarketerContractVersion $version)
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);
        abort_unless($version->contract->marketer_id === $marketer->id, 404);
        abort_if(! $version->file_url || $version->content_type !== 'pdf', 404);

        return Storage::disk('local')->download($version->file_url);
    }
}
