<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendManualNotificationJob;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Notification;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use HasDataTable;

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('notifications.view'), 403);

        $stats = [
            'total' => Notification::count(),
            'database' => Notification::where('channel', 'database')->count(),
            'push' => Notification::where('channel', 'push')->count(),
            'email' => Notification::where('channel', 'email')->count(),
        ];

        return view('admin.notifications.index', compact('stats'));
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('notifications.view'), 403);

        $query = Notification::query();

        $query = $this->applyFilters($query, $request, [
            'channel' => fn ($q, $v) => $q->where('channel', $v),
            'type' => fn ($q, $v) => $q->where('type', $v),
            'date_from' => fn ($q, $v) => $q->whereDate('created_at', '>=', $v),
            'date_to' => fn ($q, $v) => $q->whereDate('created_at', '<=', $v),
        ]);

        $columns = [
            ['searchable_columns' => ['type'], 'orderable_column' => 'type'],
            ['searchable_columns' => [], 'orderable_column' => 'channel'],
            ['searchable_columns' => [], 'orderable_column' => 'notifiable_type'],
            ['searchable_columns' => [], 'orderable_column' => null], // title
            ['searchable_columns' => [], 'orderable_column' => null], // read
            ['searchable_columns' => [], 'orderable_column' => 'sent_at'],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (Notification $row) {
            $recipientKey = Str::snake(class_basename($row->notifiable_type ?? ''));
            $recipientLabel = trans("admin.notifications_section.recipient_types.$recipientKey");
            $recipientLabel = $recipientLabel === "admin.notifications_section.recipient_types.$recipientKey"
                ? class_basename($row->notifiable_type ?? '')
                : $recipientLabel;

            $typeKey = Str::snake(class_basename($row->type ?? ''));
            $typeLabel = trans("enums/notification_type.$typeKey");
            $typeLabel = $typeLabel === "enums/notification_type.$typeKey"
                ? Str::headline(class_basename($row->type ?? ''))
                : $typeLabel;

            return [
                'type' => e($typeLabel),
                'channel' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-700">'
                    . e($row->channel?->label() ?? '—') . '</span>',
                'recipient' => e($recipientLabel) . ' · ' . e(Str::limit($row->notifiable_id ?? '', 8, '')),
                'title' => e($row->data['title'] ?? '—'),
                'read' => $row->read_at
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-700">' . e(__('admin.notifications_section.read_label')) . '</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">' . e(__('admin.notifications_section.unread_label')) . '</span>',
                'sent_at' => $row->sent_at ? $row->sent_at->format('d/m/Y H:i') : '—',
            ];
        });
    }

    // ─── Customer Notification History ───────────────────────────────────────

    public function customerNotifications(Customer $customer): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('notifications.view'), 403);

        $notifications = Notification::where('notifiable_type', Customer::class)
            ->where('notifiable_id', $customer->id)
            ->latest('sent_at')
            ->take(100)
            ->get();

        return view('admin.notifications.customer', compact('customer', 'notifications'));
    }

    // ─── Send Form ────────────────────────────────────────────────────────────

    public function create(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('notifications.send'), 403);

        $countries = Country::orderBy('name_en')->get(['id', 'name_en']);

        return view('admin.notifications.send', compact('countries'));
    }

    // ─── Send Manual Broadcast ────────────────────────────────────────────────

    public function sendManual(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('notifications.send'), 403);

        $validated = $request->validate([
            'target' => ['required', Rule::in(['all', 'country', 'specific'])],
            'country_id' => 'required_if:target,country|nullable|exists:countries,id',
            'customer_ids' => 'required_if:target,specific|nullable|array',
            'customer_ids.*' => 'uuid',
            'title_en' => 'required|string|max:100',
            'title_ar' => 'required|string|max:100',
            'body_en' => 'required|string|max:500',
            'body_ar' => 'required|string|max:500',
            'channels' => 'required|array|min:1',
            'channels.*' => Rule::in(['database', 'push', 'email']),
        ]);

        SendManualNotificationJob::dispatch(
            $validated['target'],
            $validated['country_id'] ?? null,
            $validated['customer_ids'] ?? [],
            $validated['title_en'],
            $validated['title_ar'],
            $validated['body_en'],
            $validated['body_ar'],
            $validated['channels'],
            (string) $admin->id,
        );

        return response()->json(['message' => 'Broadcast queued for delivery.']);
    }
}
