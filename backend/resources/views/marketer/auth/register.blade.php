<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إنشاء حساب ماركتر | نون</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center py-10" style="font-family:'Cairo',sans-serif;">
    <div class="w-full max-w-lg">

        <div class="text-center mb-6">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-yellow-400 items-center justify-center font-black text-2xl text-gray-900 mb-3">M</div>
            <h1 class="text-2xl font-bold text-gray-900">انضم كماركتر</h1>
            <p class="text-gray-500 text-sm mt-1">سجّل حسابك وابدأ كسب العمولات من الحملات الإعلانية</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            <form method="POST" action="{{ route('marketer.register.post') }}" class="space-y-5">
                @csrf

                {{-- Marketer Type Selection --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">نوع الحساب <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative cursor-pointer">
                            <input type="radio" name="marketer_type" value="influencer" class="sr-only peer" {{ old('marketer_type') === 'influencer' ? 'checked' : '' }}>
                            <div class="peer-checked:border-yellow-400 peer-checked:bg-yellow-50 border-2 border-gray-200 rounded-xl p-4 text-center transition">
                                <div class="text-2xl mb-1">🎬</div>
                                <div class="font-bold text-gray-900 text-sm">مؤثر</div>
                                <div class="text-gray-500 text-xs">Influencer</div>
                                <div class="text-gray-400 text-xs mt-1">رسوم ثابتة + عمولة مبيعات</div>
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="marketer_type" value="affiliate" class="sr-only peer" {{ old('marketer_type') === 'affiliate' ? 'checked' : '' }}>
                            <div class="peer-checked:border-yellow-400 peer-checked:bg-yellow-50 border-2 border-gray-200 rounded-xl p-4 text-center transition">
                                <div class="text-2xl mb-1">🔗</div>
                                <div class="font-bold text-gray-900 text-sm">أفيليت</div>
                                <div class="text-gray-500 text-xs">Affiliate</div>
                                <div class="text-gray-400 text-xs mt-1">عمولة على كل بيعة فقط</div>
                            </div>
                        </label>
                    </div>
                    @error('marketer_type') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">الاسم الكامل <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 @error('name') border-red-500 @enderror">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">الدولة <span class="text-red-500">*</span></label>
                        <select name="country_id" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
                            <option value="">اختر الدولة</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                    {{ $country->name_ar }}
                                </option>
                            @endforeach
                        </select>
                        @error('country_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">البريد الإلكتروني <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 @error('email') border-red-500 @enderror">
                    @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">رقم الهاتف</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">واتساب (للحملات)</label>
                        <input type="text" name="whatsapp_for_campaigns" value="{{ old('whatsapp_for_campaigns') }}"
                               placeholder="+966XXXXXXXXX"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">كلمة المرور <span class="text-red-500">*</span></label>
                        <input type="password" name="password" required minlength="8"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
                        @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">تأكيد كلمة المرور <span class="text-red-500">*</span></label>
                        <input type="password" name="password_confirmation" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
                    </div>
                </div>

                <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold py-3 rounded-lg text-sm transition">
                    إنشاء الحساب
                </button>
            </form>
        </div>

        <p class="text-center text-sm text-gray-500 mt-4">
            لديك حساب بالفعل؟
            <a href="{{ route('marketer.login') }}" class="text-yellow-600 font-semibold hover:underline">تسجيل الدخول</a>
        </p>
    </div>
</body>
</html>
