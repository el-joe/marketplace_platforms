@extends('layouts.travel-agency')

@section('title', __('travel.support.new_ticket'))

@section('content')
<div class="max-w-2xl space-y-5">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.support.new_ticket') }}</h1>
        <a href="{{ route('travel-agency.support.index') }}" class="text-sm text-gray-500 hover:underline">
            {{ __('travel.support.back_to_list') }}
        </a>
    </div>

    @if ($errors->any())
    <div class="bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 text-sm text-rose-700">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('travel-agency.support.store') }}" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('travel.support.subject') }}</label>
            <input type="text" name="subject_en" value="{{ old('subject_en') }}" required maxlength="255"
                   placeholder="{{ __('travel.support.subject_placeholder') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('travel.support.priority') }}</label>
            <select name="priority" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none bg-white">
                <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>{{ __('travel.support.priority_low') }}</option>
                <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>{{ __('travel.support.priority_medium') }}</option>
                <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('travel.support.priority_high') }}</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('travel.support.message') }}</label>
            <textarea name="message" rows="6" required maxlength="5000"
                      placeholder="{{ __('travel.support.message_placeholder') }}"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">{{ old('message') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('travel.support.attachment') }}</label>
            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"
                   class="w-full text-sm text-gray-600 file:me-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition-colors">
                {{ __('travel.support.submit') }}
            </button>
        </div>
    </form>
</div>
@endsection
