<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeMessage;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DisputeController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function vendorAdmin()
    {
        return Auth::guard('vendor')->user();
    }

    private function vendorId(): string
    {
        return $this->vendorAdmin()->vendor_id;
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * Disputes list — redirects to the support index with the disputes tab active.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('partner.support.tickets.index', ['tab' => 'disputes']);
    }

    /**
     * Show a single dispute conversation.
     * Only non-internal messages are visible to the vendor.
     */
    public function show(string $disputeNumber): View
    {
        $dispute = Dispute::where('dispute_number', $disputeNumber)
            ->where('vendor_id', $this->vendorId())
            ->firstOrFail();

        $messages = $dispute->messages()
            ->where('is_internal_note', false)
            ->oldest('created_at')
            ->get();

        $evidence = $dispute->evidence()
            ->with('files')
            ->oldest('created_at')
            ->get();

        return view('partner.disputes.show', compact('dispute', 'messages', 'evidence'));
    }

    /**
     * Add a seller reply to a dispute.
     */
    public function reply(Request $request, string $disputeNumber): JsonResponse
    {
        $admin = $this->vendorAdmin();

        $dispute = Dispute::where('dispute_number', $disputeNumber)
            ->where('vendor_id', $this->vendorId())
            ->firstOrFail();

        if (in_array($dispute->status->value, ['resolved', 'closed'])) {
            return response()->json(['message' => 'النزاع مغلق أو محلول ولا يمكن إضافة ردود'], 422);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $msg = DisputeMessage::create([
            'dispute_id' => $dispute->id,
            'sender_user_id' => (string) $admin->id,
            'sender_role' => 'seller',
            'message' => $validated['message'],
            'created_at' => now(),
        ]);

        // Advance status after first seller reply
        if ($dispute->status === \App\Enums\DisputeStatus::Open) {
            $dispute->update(['status' => 'seller_responded']);
        }

        return response()->json([
            'message' => 'تم إرسال ردك بنجاح',
            'msg' => [
                'id' => $msg->id,
                'message' => $msg->message,
                'sender_role' => 'seller',
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Upload evidence for a dispute.
     * Creates dispute_evidence + a linked File record.
     */
    public function uploadEvidence(Request $request, string $disputeNumber): JsonResponse
    {
        $admin = $this->vendorAdmin();

        $dispute = Dispute::where('dispute_number', $disputeNumber)
            ->where('vendor_id', $this->vendorId())
            ->firstOrFail();

        if (in_array($dispute->status->value, ['resolved', 'closed'])) {
            return response()->json(['message' => 'النزاع مغلق أو محلول'], 422);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'description' => 'nullable|string|max:500',
        ]);

        $uploadedFile = $request->file('file');
        $ext = $uploadedFile->getClientOriginalExtension();
        $path = $uploadedFile->storeAs(
            "dispute-evidence/{$dispute->id}",
            now()->format('YmdHis') . "__{$ext}",
            'public'
        );

        // Insert evidence record using DB to avoid missing updated_at column
        $evidenceId = (string) Str::uuid();
        DB::table('dispute_evidence')->insert([
            'id' => $evidenceId,
            'dispute_id' => $dispute->id,
            'uploaded_by_user_id' => (string) $admin->id,
            'description' => $request->input('description'),
            'created_at' => now(),
        ]);

        // Link the file via the morph relationship
        $mime = $uploadedFile->getMimeType();
        $fileType = str_contains($mime, 'pdf') ? 'document' : 'image';
        File::create([
            'path' => $path,
            'storage_type' => 'public',
            'file_type' => $fileType,
            'mime_type' => $mime,
            'extension' => $ext,
            'size' => $uploadedFile->getSize(),
            'model_type' => DisputeEvidence::class,
            'model_id' => $evidenceId,
        ]);

        return response()->json([
            'message' => 'تم رفع الدليل بنجاح',
            'evidence' => [
                'id' => $evidenceId,
                'description' => $request->input('description'),
                'file_url' => asset("storage/{$path}"),
                'file_type' => $fileType,
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
