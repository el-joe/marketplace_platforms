<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use Illuminate\Http\Request;

class MarketerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $marketers = Marketer::query()
            ->with(['country', 'approvedBy'])
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->type, fn ($q) => $q->where('marketer_type', $request->type))
            ->when($request->status, fn ($q) => $q->where('global_status', $request->status))
            ->withCount('invitations')
            ->latest()
            ->paginate(20);

        $pendingCount = Marketer::where('global_status', 'pending')->count();

        return view('admin.marketers.index', compact('marketers', 'pendingCount'));
    }

    public function show(Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $marketer->load([
            'country',
            'approvedBy',
            'marketerProfile',
            'invitations.campaign.vendor',
        ]);

        return view('admin.marketers.show', compact('marketer'));
    }

    public function approve(Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);
        abort_unless((string) $marketer->global_status === 'pending', 422, 'الحساب ليس في حالة معلّقة.');

        $marketer->update([
            'global_status'        => 'active',
            'approved_at'          => now(),
            'approved_by_admin_id' => auth('admin')->id(),
        ]);

        // Notify marketer admin (first owner)
        $owner = $marketer->marketerAdmins()->where('is_owner', true)->first();
        // TODO: send email/notification to $owner

        return back()->with('success', 'تم تفعيل حساب الماركتر بنجاح.');
    }

    public function reject(Request $request, Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $marketer->update([
            'global_status'    => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return back()->with('success', 'تم رفض طلب الماركتر.');
    }

    public function suspend(Request $request, Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $marketer->update([
            'global_status'    => 'suspended',
            'rejection_reason' => $request->reason,
        ]);

        return back()->with('success', 'تم تعليق الحساب.');
    }

    public function activate(Marketer $marketer)
    {
        abort_unless(auth('admin')->user()->can('marketers.manage'), 403);

        $marketer->update(['global_status' => 'active']);

        return back()->with('success', 'تم تفعيل الحساب.');
    }
}
