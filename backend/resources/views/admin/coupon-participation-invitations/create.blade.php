@extends('layouts.admin')

@section('title', 'دعوة مشاركة جديدة')

@section('content')
<div class="max-w-2xl">
    <h1 class="text-lg font-bold text-gray-900 mb-4">دعوة مشاركة جديدة</h1>

    <form method="POST" action="{{ route('admin.coupon-participation-invitations.store') }}" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">العنوان</label>
            <input type="text" name="title" value="{{ old('title') }}" class="input w-full" />
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">الوصف</label>
            <textarea name="description" rows="3" class="input w-full">{{ old('description') }}</textarea>
            @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">قسيمة مرتبطة (اختياري — اتركه فارغًا لإنشاء قسيمة تلقائيًا عند اكتمال المشاركين)</label>
            <input type="text" name="coupon_id" value="{{ old('coupon_id') }}" class="input w-full" placeholder="Coupon ID" />
            @error('coupon_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">الحد الأقصى للمشاركين</label>
                <input type="number" name="max_participants" min="1" value="{{ old('max_participants') }}" class="input w-full" required />
                @error('max_participants') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">الحد الأدنى لرسوم الاشتراك</label>
                <input type="number" name="min_fee_amount" min="0" value="{{ old('min_fee_amount') }}" class="input w-full" required />
                @error('min_fee_amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">العملة</label>
                <input type="text" name="currency" maxlength="3" value="{{ old('currency', 'SAR') }}" class="input w-full" required />
                @error('currency') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">آخر موعد للتسجيل</label>
                <input type="datetime-local" name="registration_deadline" value="{{ old('registration_deadline') }}" class="input w-full" required />
                @error('registration_deadline') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="btn btn-primary">إنشاء الدعوة</button>
        </div>
    </form>
</div>
@endsection
