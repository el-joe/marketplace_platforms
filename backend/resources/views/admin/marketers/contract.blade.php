@extends('layouts.admin')
@section('title', 'Contract — '.$marketer->name)
@section('page-title', 'Contract — '.$marketer->name)

@section('content')
<div class="max-w-3xl space-y-6">

    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-900">Contract for {{ $marketer->name }}</h2>
        <a href="{{ route('admin.marketers.contract.acceptances', $marketer) }}"
           class="text-sm px-3 py-1.5 rounded border text-gray-600 hover:bg-gray-50">
            {{ __('admin.static_text.admin_marketers_contract.view_acceptance_log') }}
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg p-3">{{ session('success') }}</div>
    @endif

    @php $activeVersion = $contract->versions->firstWhere('is_active', true); @endphp

    @if(auth('admin')->user()->can('marketers.manage'))
    <form method="POST" action="{{ route('admin.marketers.contract.required', $marketer) }}" class="bg-white rounded-xl border p-4 flex items-center gap-3">
        @csrf
        <input type="hidden" name="is_required" value="0">
        <label class="text-sm flex items-center gap-2"><input type="checkbox" name="is_required" value="1" @checked($contract->is_required) onchange="this.form.submit()"> {{ __('admin.static_text.admin_marketers_contract.contract_required_at_checkout') }}</label>
    </form>
    @endif

    <div class="bg-white rounded-xl border p-6">
        @if($activeVersion)
            <div class="flex items-center justify-between mb-3">
                <div class="font-semibold text-gray-900">
                    Active Contract — Version {{ $activeVersion->version_number }}
                </div>
                <span class="text-xs text-gray-400">Uploaded {{ $activeVersion->created_at->format('d M Y H:i') }}</span>
            </div>

            @if($activeVersion->content_type === 'pdf')
                <a href="{{ route('admin.marketers.contract.download', [$marketer, $activeVersion]) }}"
                   class="inline-block text-sm px-3 py-1.5 rounded border text-blue-600 hover:bg-blue-50">
                    {{ __('admin.static_text.admin_marketers_contract.download_pdf') }}
                </a>
            @else
                <div class="border rounded-lg p-3 bg-gray-50 text-sm max-h-72 overflow-auto">
                    {!! nl2br(e($activeVersion->text_content)) !!}
                </div>
            @endif
        @else
            <div class="text-sm text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                {{ __('admin.static_text.admin_marketers_contract.no_contract_uploaded_yet') }}
            </div>
        @endif
    </div>

        <div class="bg-white rounded-xl border p-6">
        <div class="font-semibold text-gray-900 mb-3">{{ __('admin.static_text.admin_marketers_contract.version_history') }}</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-1.5 pr-3">Version</th>
                    <th class="py-1.5 pr-3">Type</th>
                    <th class="py-1.5 pr-3">Uploaded</th>
                    <th class="py-1.5 pr-3">Status</th>
                    <th class="py-1.5 pr-3">Acceptances</th>
                    <th class="py-1.5 pr-3">File</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contract->versions as $v)
                <tr class="border-b last:border-0">
                    <td class="py-1.5 pr-3">v{{ $v->version_number }}</td>
                    <td class="py-1.5 pr-3">{{ $v->content_type }}</td>
                    <td class="py-1.5 pr-3">{{ $v->created_at->format('d M Y H:i') }}</td>
                    <td class="py-1.5 pr-3">
                        @if($v->is_active)
                            <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700">active</span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-500">inactive</span>
                        @endif
                    </td>
                    <td class="py-1.5 pr-3">{{ $v->acceptances_count }}</td>
                    <td class="py-1.5 pr-3">
                        @if($v->content_type === 'pdf' && $v->file_url)
                            <a class="text-blue-600" href="{{ route('admin.marketers.contract.download', [$marketer, $v]) }}">Download</a>
                        @else &mdash; @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl border p-6" x-data="{ type: 'pdf' }">
        <div class="font-semibold text-gray-900 mb-3">{{ __('admin.static_text.admin_marketers_contract.upload_new_version') }}</div>
        <form method="POST" action="{{ route('admin.marketers.contract.upload', $marketer) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.static_text.admin_marketers_contract.contract_type') }}</label>
                <select name="content_type" x-model="type" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="pdf">{{ __('admin.marketers.pdf_file') }}</option>
                    <option value="text">Text (plain, escaped)</option>
                </select>
            </div>

            <div x-show="type === 'pdf'">
                <label class="block text-sm font-medium text-gray-700 mb-1">PDF File (max 10MB)</label>
                <input type="file" name="contract_file" accept=".pdf" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>

            <div x-show="type === 'text'">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.static_text.admin_marketers_contract.contract_text') }}</label>
                <textarea name="text_content" rows="8" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title (EN)</label>
                    <input type="text" name="title_en" class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title (AR)</label>
                    <input type="text" name="title_ar" dir="rtl" class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                {{ __('admin.static_text.admin_marketers_contract.uploading_a_new_version_deactivates_the') }}
            </div>

            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                {{ __('admin.static_text.admin_marketers_contract.upload_new_version') }}
            </button>
        </form>
    </div>

</div>
@endsection
