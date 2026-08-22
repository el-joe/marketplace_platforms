@extends('layouts.admin')

@section('title', __('admin.ai_dashboard_section.usage_title'))

@section('content')
<div class="p-6 space-y-6">

    <h1 class="text-xl font-bold text-gray-900">{{ __('admin.ai_dashboard_section.features_title') }}</h1>

    {{-- Queue stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Image Enhancement --}}
        <div class="bg-white rounded-2xl border p-5 space-y-3">
            <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                <span class="text-blue-500">✨</span> {{ __('admin.ai_dashboard_section.image_enhancement') }}
            </h2>
            @foreach(['queued' => 'gray', 'processing' => 'blue', 'completed' => 'green', 'failed' => 'red'] as $s => $color)
            <div class="flex justify-between text-sm">
                <span class="text-{{ $color }}-600 capitalize">{{ __('admin.ai_dashboard_section.' . $s) }}</span>
                <span class="font-semibold">{{ $imageStats[$s] }}</span>
            </div>
            @endforeach
        </div>

        {{-- Virtual Try-On --}}
        <div class="bg-white rounded-2xl border p-5 space-y-3">
            <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                <span class="text-purple-500">👗</span> {{ __('admin.ai_dashboard_section.virtual_tryon') }}
            </h2>
            @foreach(['queued' => 'gray', 'processing' => 'blue', 'completed' => 'green', 'failed' => 'red'] as $s => $color)
            <div class="flex justify-between text-sm">
                <span class="text-{{ $color }}-600 capitalize">{{ __('admin.ai_dashboard_section.' . $s) }}</span>
                <span class="font-semibold">{{ $tryonStats[$s] }}</span>
            </div>
            @endforeach
        </div>

        {{-- Video Generation --}}
        <div class="bg-white rounded-2xl border p-5 space-y-3">
            <h2 class="font-semibold text-gray-700 flex items-center gap-2">
                <span class="text-pink-500">🎬</span> {{ __('admin.ai_dashboard_section.video_generation') }}
            </h2>
            @foreach(['queued' => 'gray', 'processing' => 'blue', 'completed' => 'green', 'failed' => 'red'] as $s => $color)
            <div class="flex justify-between text-sm">
                <span class="text-{{ $color }}-600 capitalize">{{ __('admin.ai_dashboard_section.' . $s) }}</span>
                <span class="font-semibold">{{ $videoStats[$s] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Credit summary --}}
    <div class="bg-white rounded-2xl border p-5 space-y-4">
        <h2 class="font-semibold text-gray-700">{{ __('admin.ai_dashboard_section.credit_pool_summary') }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-start text-gray-500 border-b">
                        <th class="pb-2 font-medium">{{ __('admin.ai_dashboard_section.feature') }}</th>
                        <th class="pb-2 font-medium">{{ __('admin.ai_dashboard_section.total_remaining') }}</th>
                        <th class="pb-2 font-medium">{{ __('admin.ai_dashboard_section.total_used') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach(['image_enhancement' => __('admin.ai_dashboard_section.image_enhancement'), 'virtual_tryon' => __('admin.ai_dashboard_section.virtual_tryon'), 'video_generation' => __('admin.ai_dashboard_section.video_generation')] as $key => $label)
                    <tr>
                        <td class="py-2">{{ $label }}</td>
                        <td class="py-2 font-semibold">{{ $creditSummary[$key]->total_remaining ?? 0 }}</td>
                        <td class="py-2 text-gray-500">{{ $creditSummary[$key]->total_used ?? 0 }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Allocate credits --}}
    <div class="bg-white rounded-2xl border p-5 space-y-4">
        <h2 class="font-semibold text-gray-700">{{ __('admin.ai_dashboard_section.allocate_credits') }}</h2>
        <form method="POST" action="{{ route('admin.ai.credits.allocate') }}" class="flex flex-wrap gap-3">
            @csrf
            <select name="owner_type" required class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="vendor">{{ __('admin.ai_dashboard_section.vendor') }}</option>
                <option value="marketer">{{ __('admin.ai_dashboard_section.marketer') }}</option>
            </select>
            <input name="owner_id" placeholder="{{ __('admin.ai_dashboard_section.owner_uuid') }}" required
                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-72">
            <select name="feature" required class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="image_enhancement">{{ __('admin.ai_dashboard_section.image_enhancement') }}</option>
                <option value="virtual_tryon">{{ __('admin.ai_dashboard_section.virtual_tryon') }}</option>
                <option value="video_generation">{{ __('admin.ai_dashboard_section.video_generation') }}</option>
            </select>
            <input name="amount" type="number" min="1" value="10" required
                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-28">
            <input name="reset_at" type="date" placeholder="{{ __('admin.ai_dashboard_section.reset_date_optional') }}"
                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <button type="submit"
                class="bg-blue-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-blue-700">
                {{ __('admin.ai_dashboard_section.add_credits') }}
            </button>
        </form>
        @if(session('success'))
        <p class="text-sm text-green-600">{{ session('success') }}</p>
        @endif
    </div>

    {{-- Recent failures --}}
    @if($recentFailures->isNotEmpty())
    <div class="bg-white rounded-2xl border p-5 space-y-3">
        <h2 class="font-semibold text-gray-700 text-red-600">{{ __('admin.ai_dashboard_section.recent_failures') }}</h2>
        <div class="space-y-2">
            @foreach($recentFailures as $f)
            <div class="flex items-start gap-3 text-sm border-b pb-2 last:border-0">
                <span class="text-gray-400 shrink-0">{{ $f['at']->diffForHumans() }}</span>
                <span class="bg-red-100 text-red-700 text-xs rounded-full px-2 py-0.5 shrink-0">{{ $f['type'] }}</span>
                <span class="text-gray-600 truncate">{{ $f['error'] ?? __('admin.ai_dashboard_section.no_error_message') }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
