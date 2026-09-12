@extends('layouts.admin')
@section('title', 'Contract — '.$marketer->name)
@section('page-title', 'Contract — '.$marketer->name)

@section('content')
<div class="max-w-3xl space-y-6">

    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-900">Contract for {{ $marketer->name }}</h2>
        <a href="{{ route('admin.marketers.contract.acceptances', $marketer) }}"
           class="text-sm px-3 py-1.5 rounded border text-gray-600 hover:bg-gray-50">
            View Acceptance Log
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg p-3">{{ session('success') }}</div>
    @endif

    @php $activeVersion = $contract->versions->firstWhere('is_active', true); @endphp

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
                    Download PDF
                </a>
            @else
                <div class="border rounded-lg p-3 bg-gray-50 text-sm max-h-72 overflow-auto">
                    {!! nl2br(e($activeVersion->text_content)) !!}
                </div>
            @endif
        @else
            <div class="text-sm text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                No contract uploaded yet.
            </div>
        @endif
    </div>

    @if($contract->versions->count() > 1)
    <div class="bg-white rounded-xl border p-6">
        <div class="font-semibold text-gray-900 mb-3">Version History</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-1.5 pr-3">Version</th>
                    <th class="py-1.5 pr-3">Type</th>
                    <th class="py-1.5 pr-3">Uploaded</th>
                    <th class="py-1.5 pr-3">Status</th>
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
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="bg-white rounded-xl border p-6" x-data="{ type: 'pdf' }">
        <div class="font-semibold text-gray-900 mb-3">Upload New Version</div>
        <form method="POST" action="{{ route('admin.marketers.contract.upload', $marketer) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contract Type</label>
                <select name="content_type" x-model="type" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="pdf">PDF File</option>
                    <option value="text">Text / HTML</option>
                </select>
            </div>

            <div x-show="type === 'pdf'">
                <label class="block text-sm font-medium text-gray-700 mb-1">PDF File (max 10MB)</label>
                <input type="file" name="contract_file" accept=".pdf" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>

            <div x-show="type === 'text'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Contract Text</label>
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
                Uploading a new version deactivates the current version. Existing customer acceptances remain linked to their original version.
            </div>

            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                Upload New Version
            </button>
        </form>
    </div>

</div>
@endsection
