@extends('layouts.admin')
@section('title', $marketer->name)
@section('page-title', $marketer->name)

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- Info card --}}
    <div class="bg-white rounded-xl border p-6 space-y-4">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ $marketer->name }}</h2>
                <div class="text-gray-500 text-sm">{{ $marketer->email }}</div>
                @if($marketer->phone)<div class="text-gray-400 text-sm">{{ $marketer->phone }}</div>@endif
            </div>
            <div class="flex flex-col gap-2 items-end">
                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $marketer->marketer_type === 'influencer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                    {{ $marketer->marketer_type === 'influencer' ? '🎬 مؤثر' : '🔗 أفيليت' }}
                </span>
                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600">{{ (string)$marketer->global_status }}</span>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 text-sm">
            <div><span class="text-gray-400">الدولة: </span><strong>{{ $marketer->country?->name_ar ?? '-' }}</strong></div>
            <div><span class="text-gray-400">واتساب: </span><strong>{{ $marketer->whatsapp_for_campaigns ?? '-' }}</strong></div>
            <div><span class="text-gray-400">تاريخ التسجيل: </span><strong>{{ $marketer->created_at->format('Y-m-d') }}</strong></div>
            @if($marketer->approved_at)
            <div><span class="text-gray-400">تم الموافقة: </span><strong>{{ $marketer->approved_at->format('Y-m-d') }}</strong></div>
            <div class="col-span-2"><span class="text-gray-400">بواسطة: </span><strong>{{ $marketer->approvedBy?->name ?? '-' }}</strong></div>
            @endif
        </div>

        @if($marketer->rejection_reason)
        <div class="p-3 bg-red-50 text-red-700 rounded-lg text-sm">
            <strong>سبب الرفض/التعليق:</strong> {{ $marketer->rejection_reason }}
        </div>
        @endif

        {{-- Actions --}}
        <div class="flex gap-3 pt-2 border-t border-gray-100">
            @if((string)$marketer->global_status === 'pending')
                <form method="POST" action="{{ route('admin.marketers.approve', $marketer) }}">
                    @csrf
                    <button class="px-5 py-2 bg-green-500 text-white font-semibold rounded-lg text-sm hover:bg-green-600">✓ موافقة وتفعيل</button>
                </form>
                <form method="POST" action="{{ route('admin.marketers.reject', $marketer) }}" x-data x-on:submit.prevent="
                    const r = prompt('سبب الرفض:');
                    if(r){ $el.querySelector('[name=reason]').value = r; $el.submit(); }">
                    @csrf
                    <input type="hidden" name="reason">
                    <button class="px-5 py-2 bg-red-500 text-white font-semibold rounded-lg text-sm hover:bg-red-600">✕ رفض</button>
                </form>
            @elseif((string)$marketer->global_status === 'active')
                <form method="POST" action="{{ route('admin.marketers.suspend', $marketer) }}" x-data x-on:submit.prevent="
                    const r = prompt('سبب التعليق:');
                    $el.querySelector('[name=reason]').value = r || '';
                    $el.submit();">
                    @csrf
                    <input type="hidden" name="reason">
                    <button class="px-5 py-2 bg-red-100 text-red-700 font-semibold rounded-lg text-sm hover:bg-red-200">تعليق الحساب</button>
                </form>
            @elseif((string)$marketer->global_status === 'suspended')
                <form method="POST" action="{{ route('admin.marketers.activate', $marketer) }}">
                    @csrf
                    <button class="px-5 py-2 bg-green-100 text-green-700 font-semibold rounded-lg text-sm hover:bg-green-200">إعادة تفعيل</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Campaign invitations --}}
    @if($marketer->invitations->isNotEmpty())
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-4 border-b"><h3 class="font-bold text-gray-800">الحملات ({{ $marketer->invitations->count() }})</h3></div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-start">الحملة</th>
                    <th class="px-4 py-3 text-center">البائع</th>
                    <th class="px-4 py-3 text-center">الحالة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($marketer->invitations->take(10) as $inv)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $inv->campaign->title ?? substr($inv->campaign_id, 0, 8) }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $inv->campaign->vendor->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">{{ $inv->status }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>
@endsection
