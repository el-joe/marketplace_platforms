<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExclusiveContractRequest;
use App\Models\ExclusiveContract;
use App\Models\Marketer;
use Illuminate\Support\Facades\Storage;

class ExclusiveContractController extends Controller
{
    public function store(StoreExclusiveContractRequest $request, Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $validated = $request->validated();

        if ($request->hasFile('contract_file')) {
            $validated['contract_file_path'] = $request->file('contract_file')->store('exclusive-contracts', 'private');
        }

        unset($validated['contract_file']);

        ExclusiveContract::create([
            ...$validated,
            'marketer_id' => $marketer->id,
            'created_by' => auth('admin')->id(),
        ]);

        return back()->with('success', 'تم إنشاء العقد الحصري.');
    }

    public function update(StoreExclusiveContractRequest $request, Marketer $marketer, ExclusiveContract $exclusiveContract)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);
        abort_unless($exclusiveContract->marketer_id === $marketer->id, 404);

        $validated = $request->validated();

        if ($request->hasFile('contract_file')) {
            if ($exclusiveContract->contract_file_path) {
                Storage::disk('private')->delete($exclusiveContract->contract_file_path);
            }
            $validated['contract_file_path'] = $request->file('contract_file')->store('exclusive-contracts', 'private');
        }

        unset($validated['contract_file']);

        $exclusiveContract->update($validated);

        return back()->with('success', 'تم تحديث العقد الحصري.');
    }

    public function destroy(Marketer $marketer, ExclusiveContract $exclusiveContract)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);
        abort_unless($exclusiveContract->marketer_id === $marketer->id, 404);

        $exclusiveContract->update(['status' => 'revoked']);

        return back()->with('success', 'تم إلغاء العقد الحصري.');
    }
}
