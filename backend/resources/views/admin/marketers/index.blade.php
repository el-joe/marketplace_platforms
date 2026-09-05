@extends('layouts.admin')
@section('title', 'الماركترز')
@section('page-title', 'إدارة الماركترز')

@section('content')
<div class="space-y-4">

    {{-- Filter bar --}}
    <div class="bg-white rounded-xl border p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-1">بحث</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="اسم أو إيميل"
                       class="border rounded-lg px-3 py-2 text-sm w-48">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">النوع</label>
                <select name="type" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">الكل</option>
                    <option value="influencer" {{ request('type') === 'influencer' ? 'selected' : '' }}>مؤثر</option>
                    <option value="affiliate" {{ request('type') === 'affiliate' ? 'selected' : '' }}>أفيليت</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">الحالة</label>
                <select name="status" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">الكل</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>معلّق ({{ $pendingCount }})</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>موقوف</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg">بحث</button>
            @if(request()->hasAny(['search','type','status']))
                <a href="{{ route('admin.marketers.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg">إعادة تعيين</a>
            @endif
        </form>
    </div>

    @if($pendingCount > 0)
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-sm text-yellow-800 font-semibold">
        ⚠️ {{ $pendingCount }} ماركتر ينتظر الموافقة
    </div>
    @endif

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-start">الاسم</th>
                    <th class="px-4 py-3 text-center">النوع</th>
                    <th class="px-4 py-3 text-center">الدولة</th>
                    <th class="px-4 py-3 text-center">الحالة</th>
                    <th class="px-4 py-3 text-center">الحملات</th>
                    <th class="px-4 py-3 text-center">تاريخ التسجيل</th>
                    <th class="px-4 py-3 text-center">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($marketers as $marketer)
                @php
                    $statusCls = [
                        'pending'    => 'bg-yellow-100 text-yellow-700',
                        'active'     => 'bg-green-100 text-green-700',
                        'suspended'  => 'bg-red-100 text-red-700',
                        'rejected'   => 'bg-gray-100 text-gray-500',
                        'blacklisted'=> 'bg-red-200 text-red-800',
                    ][(string)$marketer->global_status] ?? 'bg-gray-100 text-gray-500';
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.marketers.show', $marketer) }}" class="font-semibold text-gray-900 hover:text-blue-600">{{ $marketer->name }}</a>
                        <div class="text-xs text-gray-400">{{ $marketer->email }}</div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $marketer->marketer_type === 'influencer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $marketer->marketer_type === 'influencer' ? 'مؤثر' : 'أفيليت' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-gray-500 text-xs">{{ $marketer->country?->name_ar ?? '-' }}</td>
                    <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 rounded text-xs {{ $statusCls }}">{{ (string)$marketer->global_status }}</span></td>
                    <td class="px-4 py-3 text-center">{{ $marketer->invitations_count }}</td>
                    <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $marketer->created_at->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-center">
                        @if((string)$marketer->global_status === 'pending')
                            <form method="POST" action="{{ route('admin.marketers.approve', $marketer) }}" class="inline">
                                @csrf
                                <button class="text-xs px-3 py-1 bg-green-500 text-white rounded-lg hover:bg-green-600">موافقة</button>
                            </form>
                        @elseif((string)$marketer->global_status === 'active')
                            <form method="POST" action="{{ route('admin.marketers.suspend', $marketer) }}" class="inline">
                                @csrf
                                <input type="hidden" name="reason" value="Admin action">
                                <button class="text-xs px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">تعليق</button>
                            </form>
                        @elseif((string)$marketer->global_status === 'suspended')
                            <form method="POST" action="{{ route('admin.marketers.activate', $marketer) }}" class="inline">
                                @csrf
                                <button class="text-xs px-3 py-1 bg-green-100 text-green-700 rounded-lg hover:bg-green-200">تفعيل</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد نتائج</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t">{{ $marketers->withQueryString()->links() }}</div>
    </div>
</div>
@endsection
