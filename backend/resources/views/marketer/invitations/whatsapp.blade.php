<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('marketer.invitations.whatsapp_title') }}</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4" style="font-family:'Cairo',sans-serif;">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-1">{{ __('marketer.invitations.whatsapp_title') }}</h1>
        <p class="text-sm text-gray-500 mb-5">@isset($invitation){{ $invitation->campaign->title ?? $invitation->campaign->vendor?->name ?? __('marketer.invitations.campaign_default_label') }}@endisset</p>

        @if(isset($expiredLink))
            <div class="p-3 rounded-lg text-sm bg-gray-50 text-gray-700">{{ __('marketer.invitations.whatsapp_link_expired') }}</div>
            <a href="{{ route('marketer.login') }}" class="block text-center mt-5 text-yellow-600 text-sm hover:underline">{{ __('marketer.invitations.whatsapp_login') }}</a>
        @elseif($outcome === 'accepted' || $outcome === 'rejected')
            <div class="p-3 rounded-lg text-sm {{ $outcome === 'accepted' ? 'bg-green-50 text-green-700' : 'bg-gray-50 text-gray-700' }}">
                {{ __('marketer.invitations.whatsapp_'.$outcome) }}
            </div>
            <a href="{{ route('marketer.dashboard') }}" class="block text-center mt-5 text-yellow-600 text-sm hover:underline">{{ __('marketer.invitations.whatsapp_login') }}</a>
        @elseif($outcome === 'error')
            <div class="p-3 rounded-lg text-sm bg-red-50 text-red-700">{{ $error ?? '' }}</div>
            <a href="{{ route('marketer.dashboard') }}" class="block text-center mt-5 text-yellow-600 text-sm hover:underline">{{ __('marketer.invitations.whatsapp_login') }}</a>
        @elseif($outcome === 'unavailable' || ! $invitation->isPending() || $invitation->isTimedOut())
            <div class="p-3 rounded-lg text-sm bg-gray-50 text-gray-700">{{ __('marketer.invitations.whatsapp_unavailable') }}</div>
        @else
            <p class="text-sm text-gray-600 mb-4">{{ __('marketer.invitations.whatsapp_hint', ['hours' => $invitation->acceptance_window_hours]) }}</p>
            <form method="POST" action="{{ request()->fullUrl() }}" class="space-y-3">
                @csrf
                @if(($action ?? null) === 'accept')
                    <input type="hidden" name="action" value="accept">
                    <button type="submit" class="w-full bg-green-600 text-white rounded-lg py-2.5 text-sm font-semibold">{{ __('marketer.invitations.whatsapp_accept') }}</button>
                @elseif(($action ?? null) === 'reject')
                    <input type="hidden" name="action" value="reject">
                    <textarea name="reason" rows="2" maxlength="500" placeholder="{{ __('marketer.invitations.whatsapp_reason') }}"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="w-full bg-gray-200 text-gray-800 rounded-lg py-2.5 text-sm font-semibold">{{ __('marketer.invitations.whatsapp_reject') }}</button>
                @else
                    <textarea name="reason" rows="2" maxlength="500" placeholder="{{ __('marketer.invitations.whatsapp_reason') }}"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                    <div class="flex gap-3">
                        <button type="submit" name="action" value="accept" class="flex-1 bg-green-600 text-white rounded-lg py-2.5 text-sm font-semibold">{{ __('marketer.invitations.whatsapp_accept') }}</button>
                        <button type="submit" name="action" value="reject" class="flex-1 bg-gray-200 text-gray-800 rounded-lg py-2.5 text-sm font-semibold">{{ __('marketer.invitations.whatsapp_reject') }}</button>
                    </div>
                @endif
            </form>
        @endif
    </div>
</body>
</html>
