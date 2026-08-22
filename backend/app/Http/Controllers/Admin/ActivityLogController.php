<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorListing;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ActivityLogController extends Controller
{
    use HasDataTable;
    use HasExport;

    /** causer_type => [label, badge class, table] */
    private const CAUSER_MAP = [
        Admin::class => ['label' => 'Admin', 'badge' => 'bg-blue-100 text-blue-700', 'table' => 'admins'],
        VendorAdmin::class => ['label' => 'VendorAdmin', 'badge' => 'bg-green-100 text-green-700', 'table' => 'vendor_admins'],
        Customer::class => ['label' => 'Customer', 'badge' => 'bg-yellow-100 text-yellow-700', 'table' => 'customers'],
    ];

    /** subject_type => short label (also drives filter dropdown options) */
    private const SUBJECT_LABELS = [
        Product::class => 'Product',
        Order::class => 'Order',
        Vendor::class => 'Vendor',
        Payout::class => 'Payout',
        VendorListing::class => 'Listing',
        Customer::class => 'Customer',
        Admin::class => 'Admin',
    ];

    private function authorizeView(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin && $admin->hasPermissionTo('activity-log.view'), 403);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizeView();

        if ($request->filled('export')) {
            return $this->exportActivityLog($request);
        }

        $today = Carbon::today();
        $lastAt = Activity::max('created_at');

        $stats = [
            'total_today' => Activity::whereDate('created_at', $today)->count(),
            'admin_actions' => Activity::where('causer_type', Admin::class)
                ->whereDate('created_at', $today)->count(),
            'deletions' => Activity::where('event', 'deleted')
                ->whereDate('created_at', $today)->count(),
            'last_activity' => $lastAt ? Carbon::parse($lastAt)->diffForHumans() : '—',
        ];

        return view('admin.activity-log.index', [
            'stats' => $stats,
            'causerTypes' => self::CAUSER_MAP,
            'subjectTypes' => self::SUBJECT_LABELS,
        ]);
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $this->authorizeView();

        $query = $this->buildActivityLogQuery($request);

        $columns = [
            0 => ['orderable_column' => 'al.created_at'],
            1 => ['searchable_columns' => ['a.name', 'va.name', 'c.name']],
            2 => ['orderable_column' => 'al.event'],
            3 => ['searchable_columns' => ['al.subject_type']],
            4 => ['searchable_columns' => ['al.description']],
            5 => ['orderable_column' => 'al.log_name'],
            6 => ['searchable_columns' => ['al.ip_address']],
            7 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            $causerInfo = self::CAUSER_MAP[$row->causer_type] ?? null;
            $causerLabel = $causerInfo['label']
                ?? ($row->causer_type ? class_basename($row->causer_type) : 'System');

            $subjectLabel = $row->subject_type
                ? (self::SUBJECT_LABELS[$row->subject_type] ?? class_basename($row->subject_type))
                : null;

            $properties = $row->properties ? json_decode($row->properties, true) : [];
            $subjectDisplay = $properties['subject_display_name']
                ?? $this->resolveSubjectDisplay($row->subject_type, $row->subject_id);

            return [
                'DT_RowId' => 'al-' . $row->id,
                'id' => $row->id,
                'created_at' => $row->created_at,
                'created_at_formatted' => Carbon::parse($row->created_at)->format('M d, Y H:i'),
                'time_ago' => Carbon::parse($row->created_at)->diffForHumans(),
                'causer_name' => $row->causer_name,
                'causer_type_label' => $causerLabel,
                'event' => $row->event,
                'subject_type_label' => $subjectLabel,
                'subject_display_name' => $subjectDisplay,
                'subject_id' => $row->subject_id,
                'description' => (string) $row->description,
                'log_name' => $row->log_name,
                'ip_address' => $row->ip_address,
            ];
        });
    }

    private function buildActivityLogQuery(Request $request): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('activity_log as al')
            ->leftJoin('admins as a', function ($j) {
                $j->on('al.causer_id', '=', 'a.id')
                    ->where('al.causer_type', '=', Admin::class);
            })
            ->leftJoin('vendor_admins as va', function ($j) {
                $j->on('al.causer_id', '=', 'va.id')
                    ->where('al.causer_type', '=', VendorAdmin::class);
            })
            ->leftJoin('customers as c', function ($j) {
                $j->on('al.causer_id', '=', 'c.id')
                    ->where('al.causer_type', '=', Customer::class);
            })
            ->select([
                'al.*',
                DB::raw('COALESCE(a.name, va.name, c.name) as causer_name'),
                DB::raw('COALESCE(a.email, va.email, c.email) as causer_email'),
            ]);

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('al.description', 'like', "%{$v}%")
                    ->orWhere('al.subject_type', 'like', "%{$v}%");
            }),
            'log_name' => fn($q, $v) => $q->where('al.log_name', 'like', "%{$v}%"),
            'event' => fn($q, $v) => $q->where('al.event', $v),
            'causer_type' => fn($q, $v) => $v === 'system'
                ? $q->whereNull('al.causer_type')
                : $q->where('al.causer_type', $v),
            'subject_type' => fn($q, $v) => $q->where('al.subject_type', $v),
            'causer_id' => fn($q, $v) => $q->where('al.causer_id', $v),
            'date_from' => fn($q, $v) => $q->whereDate('al.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('al.created_at', '<=', $v),
            'ip_address' => fn($q, $v) => $q->where('al.ip_address', 'like', "%{$v}%"),
        ]);
    }

    private function exportActivityLog(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $entries = $this->buildActivityLogQuery($request)->orderByDesc('al.created_at')->limit(10000)->get();

        $headers = ['Event', 'Description', 'Admin', 'Subject', 'Date'];

        $rows = $entries->map(function ($row) {
            $causerInfo = self::CAUSER_MAP[$row->causer_type] ?? null;
            $causerLabel = $row->causer_name ?? ($causerInfo['label'] ?? ($row->causer_type ? class_basename($row->causer_type) : 'System'));

            $subjectLabel = $row->subject_type
                ? (self::SUBJECT_LABELS[$row->subject_type] ?? class_basename($row->subject_type))
                : null;

            return [
                $row->event,
                $row->description,
                $causerLabel,
                $subjectLabel,
                Carbon::parse($row->created_at)->format('d M Y H:i'),
            ];
        });

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('activity_log', $headers, $rows),
            'csv' => $this->exportCsv('activity_log', $headers, $rows),
            'word' => $this->exportWord('activity_log', 'Activity Log', $rows),
            default => abort(400, __('admin.invalid_export_format')),
        };
    }

    /**
     * Per-row subject display resolver. Falls back to a short UUID slice.
     * Causes up to N queries per page (default 50). For hot paths, write
     * properties.subject_display_name via ActivityLoggerService to avoid this.
     */
    private function resolveSubjectDisplay(?string $type, ?string $id): ?string
    {
        if (!$type || !$id) {
            return null;
        }

        return match ($type) {
            Product::class => optional(Product::find($id))->name_en,
            Order::class => optional(Order::find($id))->order_number,
            Vendor::class => optional(Vendor::find($id))->store_name,
            Payout::class => optional(Payout::find($id))->payout_number,
            VendorListing::class => 'Listing #' . Str::limit($id, 8, ''),
            Customer::class => optional(Customer::find($id))->name,
            Admin::class => optional(Admin::find($id))->name,
            VendorAdmin::class => optional(VendorAdmin::find($id))->name,
            default => Str::limit($id, 12, ''),
        };
    }

    // ─── Detail (JSON for modal, HTML for direct URL) ─────────────────────────

    public function show(Request $request, string $id): mixed
    {
        $this->authorizeView();

        $entry = Activity::findOrFail($id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['data' => $this->serializeEntry($entry)]);
        }

        return view('admin.activity-log.show', compact('entry'));
    }

    private function serializeEntry(Activity $entry): array
    {
        $causerInfo = self::CAUSER_MAP[$entry->causer_type] ?? null;
        $causerLabel = $causerInfo['label']
            ?? ($entry->causer_type ? class_basename($entry->causer_type) : 'System');

        $causerName = $causerEmail = null;
        if ($entry->causer_type && $entry->causer_id && $causerInfo) {
            $row = DB::table($causerInfo['table'])
                ->where('id', $entry->causer_id)
                ->select('name', 'email')
                ->first();
            $causerName = $row?->name;
            $causerEmail = $row?->email;
        }

        $properties = $entry->properties ?? [];
        $subjectLabel = $entry->subject_type
            ? (self::SUBJECT_LABELS[$entry->subject_type] ?? class_basename($entry->subject_type))
            : null;
        $subjectDisplay = $properties['subject_display_name']
            ?? $this->resolveSubjectDisplay($entry->subject_type, $entry->subject_id);

        return [
            'id' => $entry->id,
            'event' => $entry->event,
            'description' => $entry->description,
            'log_name' => $entry->log_name,
            'batch_uuid' => $entry->batch_uuid,
            'ip_address' => $entry->ip_address,
            'user_agent' => $entry->user_agent,
            'created_at_formatted' => $entry->created_at?->format('M d, Y H:i:s T'),
            'time_ago' => $entry->created_at?->diffForHumans(),
            'causer_name' => $causerName,
            'causer_email' => $causerEmail,
            'causer_type_label' => $causerLabel,
            'subject_type_label' => $subjectLabel,
            'subject_display_name' => $subjectDisplay,
            'subject_id' => $entry->subject_id,
            'subject_url' => $this->resolveSubjectUrl($entry->subject_type, $entry->subject_id),
            'properties' => $properties,
        ];
    }

    private function resolveSubjectUrl(?string $type, ?string $id): ?string
    {
        if (!$type || !$id) {
            return null;
        }

        $routeName = match ($type) {
            Product::class => 'admin.products.show',
            Order::class => 'admin.orders.show',
            Vendor::class => 'admin.vendors.show',
            Payout::class => 'admin.payouts.show',
            Customer::class => 'admin.customers.show',
            default => null,
        };

        if (!$routeName || !Route::has($routeName)) {
            return null;
        }

        try {
            return route($routeName, $id);
        } catch (\Throwable) {
            return null;
        }
    }

    // ─── Async causer search (Select2 source) ─────────────────────────────────

    public function causerSearch(Request $request): JsonResponse
    {
        $this->authorizeView();

        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $like = '%' . $q . '%';
        $results = [];

        foreach (self::CAUSER_MAP as $info) {
            $rows = DB::table($info['table'])
                ->select('id', 'name', 'email')
                ->where(fn($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(10)
                ->get();

            foreach ($rows as $r) {
                $results[] = [
                    'id' => $r->id,
                    'text' => $r->name . ' — ' . $info['label'] . ' (' . $r->email . ')',
                    'name' => $r->name,
                    'email' => $r->email,
                    'type' => $info['label'],
                ];
            }
        }

        return response()->json(['data' => $results]);
    }
}
