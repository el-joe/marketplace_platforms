@extends('layouts.admin')

@section('title', __('admin.portal_content.title_with_page', ['page' => $pageKey]))

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.portal_content.page_prefix') }}: {{ $pageKey }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.portal_content.edit_subtitle') }}</p>
        </div>
        <a href="{{ route('admin.portal-content.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <x-heroicon name="arrow-uturn-left" class="w-4 h-4" />
            {{ __('admin.portal_content.back_to_pages') }}
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.portal-content.save', $pageKey) }}" class="space-y-6" enctype="multipart/form-data">
        @csrf

        @forelse($blocks as $blockKey => $rows)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-200">
                    <h2 class="text-sm font-semibold text-gray-700">{{ $blockKey }}</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                        <div class="px-4 py-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-mono text-gray-400">{{ $row->field_key }} <span class="ms-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">{{ $row->type->value }}</span></div>
                                <label class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                    <input type="checkbox" name="fields[{{ $row->id }}][is_active]" value="1" {{ $row->is_active ? 'checked' : '' }} class="rounded border-gray-300">
                                    {{ __('common.active') }}
                                </label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.portal_content.english') }}</label>
                                    @if($row->type === \App\Enums\PortalContentType::RichText)
                                        <textarea name="fields[{{ $row->id }}][value_en]" rows="4" class="w-full rounded-lg border-gray-300 text-sm">{{ old("fields.{$row->id}.value_en", $row->value_en) }}</textarea>
                                    @else
                                        <input type="text" name="fields[{{ $row->id }}][value_en]" value="{{ old("fields.{$row->id}.value_en", $row->value_en) }}" class="w-full rounded-lg border-gray-300 text-sm" dir="ltr">
                                    @endif
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.portal_content.arabic') }}</label>
                                    @if($row->type === \App\Enums\PortalContentType::RichText)
                                        <textarea name="fields[{{ $row->id }}][value_ar]" rows="4" class="w-full rounded-lg border-gray-300 text-sm" dir="rtl">{{ old("fields.{$row->id}.value_ar", $row->value_ar) }}</textarea>
                                    @else
                                        <input type="text" name="fields[{{ $row->id }}][value_ar]" value="{{ old("fields.{$row->id}.value_ar", $row->value_ar) }}" class="w-full rounded-lg border-gray-300 text-sm" dir="rtl">
                                    @endif
                                </div>
                            </div>

                            @if($row->type === \App\Enums\PortalContentType::Link)
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.portal_content.url') }}</label>
                                    <input type="text" name="fields[{{ $row->id }}][value_url]" value="{{ old("fields.{$row->id}.value_url", $row->value_url) }}" class="w-full rounded-lg border-gray-300 text-sm font-mono" dir="ltr">
                                </div>
                            @endif

                            @if($row->type === \App\Enums\PortalContentType::Image)
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.portal_content.image_file') }}</label>
                                    <input type="file" name="fields[{{ $row->id }}][value_file]" accept="image/*" class="w-full rounded-lg border-gray-300 text-sm">
                                    @error("fields.{$row->id}.value_file")
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                    @if($row->value_url)
                                        <img src="{{ \Illuminate\Support\Str::startsWith($row->value_url, ['http://', 'https://']) ? $row->value_url : \Illuminate\Support\Facades\Storage::disk('public')->url($row->value_url) }}"
                                             alt="" class="mt-2 h-16 rounded border border-gray-200 object-cover">
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center text-sm text-gray-400">
                {{ __('admin.portal_content.no_fields') }}
            </div>
        @endforelse

        <div class="sticky bottom-0 z-10 -mx-6 border-t border-gray-200 bg-white px-6 py-4 shadow-md flex items-center justify-end">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-blue-700">
                <x-heroicon name="check-circle" class="w-4 h-4" />
                {{ __('admin.portal_content.save_page') }}
            </button>
        </div>
    </form>

</div>
@endsection
