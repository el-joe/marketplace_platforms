@extends('layouts.admin')
@section('title', 'Contract Acceptances — '.$marketer->name)
@section('page-title', 'Contract Acceptances — '.$marketer->name)

@section('content')
<div class="max-w-5xl space-y-6">

    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-900">Acceptance Log — {{ $marketer->name }}</h2>
        <a href="{{ route('admin.marketers.contract.show', $marketer) }}"
           class="text-sm px-3 py-1.5 rounded border text-gray-600 hover:bg-gray-50">
            Back to Contract
        </a>
    </div>

    <form method="GET" class="bg-white rounded-xl border p-3 flex flex-wrap gap-3 items-end text-sm">
        <div><label class="block text-xs text-gray-500">Version</label>
            <select name="version" class="border rounded px-2 py-1">
                <option value="">All</option>
                @foreach($versions as $v)
                    <option value="{{ $v->id }}" @selected(request('version') == $v->id)>v{{ $v->version_number }}</option>
                @endforeach
            </select></div>
        <div><label class="block text-xs text-gray-500">From</label><input type="date" name="from" value="{{ request('from') }}" class="border rounded px-2 py-1"></div>
        <div><label class="block text-xs text-gray-500">To</label><input type="date" name="to" value="{{ request('to') }}" class="border rounded px-2 py-1"></div>
        <button class="px-3 py-1.5 rounded bg-blue-600 text-white">Filter</button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-3 py-1.5 rounded border text-gray-600">Export CSV</a>
    </form>

    @unless($contract)
        <div class="text-sm text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg p-3">No contract has been created for this marketer yet.</div>
    @endunless

    <div class="bg-white rounded-xl border overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-2 px-3">Acceptance ID</th>
                    <th class="py-2 px-3">Customer</th>
                    <th class="py-2 px-3">Version</th>
                    <th class="py-2 px-3">Accepted At</th>
                    <th class="py-2 px-3">IP Address</th>
                    <th class="py-2 px-3">Order</th>
                </tr>
            </thead>
            <tbody>
                @forelse($acceptances as $acc)
                <tr class="border-b last:border-0">
                    <td class="py-2 px-3"><code class="text-xs text-gray-500">{{ $acc->id }}</code></td>
                    <td class="py-2 px-3">{{ $acc->customer->name ?? '—' }}</td>
                    <td class="py-2 px-3">
                        <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">v{{ ($acc->contractVersion->version_number ?? '?') }}</span>
                    </td>
                    <td class="py-2 px-3">{{ optional($acc->accepted_at)->format('d M Y H:i:s') }}</td>
                    <td class="py-2 px-3 text-xs text-gray-500" title="{{ $acc->user_agent }}">{{ $acc->ip_address }}</td>
                    <td class="py-2 px-3">
                        @if($acc->order)
                            <a href="{{ route('admin.orders.show', $acc->order) }}" class="text-blue-600 text-xs">
                                #{{ $acc->order->order_number }}
                            </a>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="py-6 text-center text-gray-400">No acceptances yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $acceptances->links() }}</div>

</div>
@endsection
