<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\SupportTicketRequesterRole;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\SupportTicket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\TravelAgencyMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupportController extends Controller
{
    private function agencyId(): string
    {
        return Auth::guard('travel_agency')->user()->travel_agency_id;
    }

    private function tickets()
    {
        return SupportTicket::query()
            ->where('requester_user_id', $this->agencyId())
            ->where('requester_role', SupportTicketRequesterRole::TravelAgency);
    }

    public function index(Request $request): View
    {
        $query = $this->tickets()->latest('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', SupportTicketStatus::from($status));
        }

        if ($from = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $tickets = $query->paginate(20)->withQueryString();

        return view('travel-agency.support.index', compact('tickets'));
    }

    public function create(): View
    {
        return view('travel-agency.support.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_en' => ['required', 'string', 'max:255'],
            'message'    => ['required', 'string', 'max:5000'],
            'priority'   => ['required', 'in:low,medium,high'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $priorityMap = ['low' => 'low', 'medium' => 'normal', 'high' => 'high'];

        $ticket = SupportTicket::create([
            'id'                 => (string) Str::uuid(),
            'ticket_number'      => $this->generateTicketNumber(),
            'requester_user_id'  => $this->agencyId(),
            'requester_role'     => SupportTicketRequesterRole::TravelAgency,
            'category'           => 'other',
            'priority'           => $priorityMap[$validated['priority']],
            'status'             => SupportTicketStatus::Open,
            'subject'            => $validated['subject_en'],
            'description'        => $validated['message'],
        ]);

        $member = Auth::guard('travel_agency')->user();

        $message = TicketMessage::create([
            'id'         => (string) Str::uuid(),
            'ticket_id'  => $ticket->id,
            'sender_type'=> TravelAgencyMember::class,
            'sender_id'  => $member->id,
            'message'    => $validated['message'],
        ]);

        $this->storeAttachment($request, $message);

        return redirect()
            ->route('travel-agency.support.show', $ticket->ticket_number)
            ->with('success', __('travel.support.created_success'));
    }

    public function show(string $ticketNumber): View
    {
        $ticket = $this->tickets()
            ->where('ticket_number', $ticketNumber)
            ->firstOrFail();

        $messages = $ticket->messages()
            ->where('is_internal_note', false)
            ->with('attachments.files')
            ->oldest('created_at')
            ->get();

        return view('travel-agency.support.show', compact('ticket', 'messages'));
    }

    public function reply(Request $request, string $ticketNumber): RedirectResponse
    {
        $ticket = $this->tickets()
            ->where('ticket_number', $ticketNumber)
            ->firstOrFail();

        if (in_array($ticket->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true)) {
            return back()->withErrors(['message' => __('travel.support.ticket_closed_notice')]);
        }

        $validated = $request->validate([
            'message'    => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $member = Auth::guard('travel_agency')->user();

        $message = TicketMessage::create([
            'id'          => (string) Str::uuid(),
            'ticket_id'   => $ticket->id,
            'sender_type' => TravelAgencyMember::class,
            'sender_id'   => $member->id,
            'message'     => $validated['message'],
        ]);

        $this->storeAttachment($request, $message);

        if ($ticket->status === SupportTicketStatus::WaitingCustomer) {
            $ticket->update(['status' => SupportTicketStatus::Open]);
        }

        return back()->with('success', __('travel.support.reply_success'));
    }

    private function storeAttachment(Request $request, TicketMessage $message): void
    {
        if (!$request->hasFile('attachment')) {
            return;
        }

        $uploadedFile = $request->file('attachment');
        $ext = $uploadedFile->getClientOriginalExtension();
        $path = $uploadedFile->storeAs(
            "support-tickets/{$message->ticket_id}",
            now()->format('YmdHis') . '_' . Str::random(8) . ".{$ext}",
            'public'
        );

        $attachment = TicketAttachment::create([
            'id'                 => (string) Str::uuid(),
            'ticket_message_id'  => $message->id,
        ]);

        $mime = $uploadedFile->getMimeType();

        File::create([
            'path'         => $path,
            'storage_type' => 'public',
            'file_type'    => str_contains($mime, 'pdf') ? 'document' : 'image',
            'mime_type'    => $mime,
            'extension'    => $ext,
            'size'         => $uploadedFile->getSize(),
            'model_type'   => TicketAttachment::class,
            'model_id'     => $attachment->id,
        ]);
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = 'TKT-' . date('ymd') . '-' . strtoupper(Str::random(5));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }
}
