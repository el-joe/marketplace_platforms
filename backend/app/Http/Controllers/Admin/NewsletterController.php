<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class NewsletterController extends Controller
{
    public function index(): View
    {
        $totalActive       = NewsletterSubscriber::where('status', 'active')->count();
        $totalUnsubscribed = NewsletterSubscriber::where('status', 'unsubscribed')->count();
        $last30Days        = NewsletterSubscriber::where('status', 'active')
            ->where('created_at', '>=', now()->subDays(30))->count();

        return view('admin.newsletter.index', compact(
            'totalActive', 'totalUnsubscribed', 'last30Days'
        ));
    }

    public function datatable(Request $request): JsonResponse
    {
        $q = $request->input('search.value', '');

        $query = NewsletterSubscriber::with('country:id,site_code,name_en')
            ->when($q, fn ($builder) =>
                $builder->where('email', 'like', "%{$q}%")
            )
            ->when($request->input('status'), fn ($builder, $status) =>
                $builder->where('status', $status)
            )
            ->when($request->input('country_id'), fn ($builder, $cid) =>
                $builder->where('country_id', $cid)
            );

        $total    = NewsletterSubscriber::count();
        $filtered = $query->count();
        $rows     = $query->orderByDesc('created_at')
            ->offset((int) $request->input('start', 0))
            ->limit((int) $request->input('length', 25))
            ->get();

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn ($s) => [
                'id'            => $s->id,
                'email'         => $s->email,
                'country'       => $s->country?->site_code,
                'source'        => $s->source,
                'locale'        => $s->locale,
                'status'        => $s->status,
                'subscribed_at' => $s->created_at?->format('Y-m-d H:i'),
                'customer_id'   => $s->customer_id,
            ]),
        ]);
    }

    public function export(Request $request)
    {
        $status     = $request->input('status', 'active');
        $countryId  = $request->input('country_id');

        $rows = NewsletterSubscriber::where('status', $status)
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->orderByDesc('created_at')
            ->get(['email', 'country_id', 'locale', 'source', 'created_at']);

        $csv  = "Email,Country,Locale,Source,Subscribed At\n";
        foreach ($rows as $r) {
            $csv .= "\"{$r->email}\",\"{$r->country_id}\",\"{$r->locale}\",\"{$r->source}\",\"{$r->created_at}\"\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=newsletter-subscribers-{$status}.csv",
        ]);
    }

    public function destroy(NewsletterSubscriber $subscriber): JsonResponse
    {
        $subscriber->delete();
        return response()->json(['success' => true]);
    }
}
