@extends('layouts.marketer')
@section('title', 'البروفايل')
@section('page-title', 'بروفايل الماركتر')

@section('content')
<div class="max-w-2xl space-y-6">

    {{-- Profile Info Card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 rounded-full bg-yellow-400 flex items-center justify-center font-black text-2xl text-gray-900">
                {{ mb_substr($marketer->name, 0, 1) }}
            </div>
            <div>
                <div class="font-bold text-gray-900 text-lg">{{ $marketer->name }}</div>
                <div class="text-gray-500 text-sm">{{ $marketer->email }}</div>
                <span class="inline-flex mt-1 px-2 py-0.5 rounded text-xs font-semibold
                    {{ $marketer->isInfluencer() ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                    {{ $marketer->isInfluencer() ? '🎬 مؤثر' : '🔗 أفيليت' }}
                </span>
            </div>
        </div>

        {{-- QR Code & Referral Slug --}}
        @if($profile->qr_code_path)
        <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg mb-5">
            <img src="{{ Storage::url($profile->qr_code_path) }}" alt="QR Code" class="w-24 h-24 rounded-lg">
            <div>
                <div class="text-xs text-gray-500 mb-1">رابط البروفايل العام:</div>
                <code class="text-sm font-mono text-gray-700">/m/{{ $profile->profile_slug }}</code>
            </div>
        </div>
        @endif

        <form method="POST" action="{{ route('marketer.profile.update') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">رقم واتساب (للحملات)</label>
                <input type="text" name="whatsapp_for_campaigns" value="{{ old('whatsapp_for_campaigns', $marketer->whatsapp_for_campaigns) }}"
                       placeholder="+966XXXXXXXXX"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">نبذة عنك (عربي)</label>
                <textarea name="bio_ar" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">{{ old('bio_ar', $profile->bio_ar) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Bio (English)</label>
                <textarea name="bio_en" rows="3" dir="ltr" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">{{ old('bio_en', $profile->bio_en) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">رابط فيديو (يوتيوب / انستقرام)</label>
                <input type="url" name="video_url" value="{{ old('video_url', $profile->video_url) }}" dir="ltr"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">صورة البانر</label>
                @if($profile->bannerFile)
                    <img src="{{ Storage::url($profile->bannerFile->path) }}" class="h-24 rounded-lg mb-2 object-cover">
                @endif
                <input type="file" name="banner" accept="image/*" class="text-sm text-gray-600">
            </div>

            <button type="submit" class="px-6 py-2.5 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold rounded-lg text-sm">
                حفظ التغييرات
            </button>
        </form>
    </div>

    {{-- Performance summary --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-bold text-gray-800 mb-4">إحصائيات عامة</h3>
        <div class="grid grid-cols-3 gap-4 text-center">
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-lg font-black text-gray-900">{{ number_format($profile->total_campaigns) }}</div>
                <div class="text-xs text-gray-500">حملات</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-lg font-black text-gray-900">{{ number_format($profile->total_conversions) }}</div>
                <div class="text-xs text-gray-500">تحويلات</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-lg font-black text-green-600">{{ number_format($profile->total_earnings) }}</div>
                <div class="text-xs text-gray-500">أرباح</div>
            </div>
        </div>
    </div>

</div>
@endsection
