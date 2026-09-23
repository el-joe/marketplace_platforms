@extends('layouts.marketer')

@section('content')
<div class="max-w-2xl mx-auto py-8">
    <h1 class="text-xl font-bold mb-4">{{ __('marketer.choose_account_type') }}</h1>
    <form method="POST" action="{{ route('marketer.onboarding.job-type') }}" class="space-y-4">
        @csrf
        <div class="grid grid-cols-2 gap-3">
            @foreach ($jobKeys as $key)
                <label class="cursor-pointer">
                    <input type="checkbox" name="job_keys[]" value="{{ $key }}" class="sr-only peer"
                           {{ in_array($key, old('job_keys', $selected)) ? 'checked' : '' }}>
                    <div class="peer-checked:border-yellow-400 peer-checked:bg-yellow-50 border-2 border-gray-200 rounded-xl p-5 text-center">
                        <div class="font-bold">{{ $key === 'affiliate' ? __('marketer.broker') : ($key === 'influencer' ? __('marketer.influencer') : $key) }}</div>
                    </div>
                </label>
            @endforeach
        </div>
        @error('job_keys') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
        @error('job_keys.*') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
        <button class="bg-yellow-400 text-gray-900 font-bold rounded-lg px-6 py-2">{{ __('marketer.next') }}</button>
    </form>
</div>
@endsection
