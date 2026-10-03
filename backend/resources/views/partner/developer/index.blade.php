@extends('layouts.partner')

@section('title', 'Developer API')
@section('page-title', 'Developer API')

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8" x-data="developerPage()" x-init="init()">

    {{-- One-time token reveal --}}
    @if(session('new_token'))
    <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-5">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-green-800">Token generated — copy it now</p>
                <p class="text-sm text-green-700 mt-0.5">This is the only time it will be displayed. Store it securely.</p>
                <div class="mt-3 flex items-center gap-2">
                    <code id="new-token-value" class="flex-1 break-all rounded-lg bg-green-100 px-3 py-2 text-sm font-mono text-green-900 select-all">{{ session('new_token') }}</code>
                    <button onclick="copyToken()" class="flex-shrink-0 rounded-lg border border-green-300 bg-white px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-50 transition">Copy</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(session('success') && !session('new_token'))
    <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800 text-sm font-medium">{{ session('success') }}</div>
    @endif

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Developer API</h1>
        <p class="mt-1 text-sm text-gray-500">Manage your permanent API tokens and browse the Partner API documentation.</p>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex gap-6">
            <button @click="tab = 'tokens'" :class="tab === 'tokens' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="border-b-2 pb-3 text-sm font-medium transition whitespace-nowrap">
                API Tokens
            </button>
            <button @click="tab = 'docs'" :class="tab === 'docs' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="border-b-2 pb-3 text-sm font-medium transition whitespace-nowrap">
                Documentation
            </button>
        </nav>
    </div>

    {{-- ──────────────────────────────────────────────────────────────────────── --}}
    {{-- TAB: TOKENS --}}
    {{-- ──────────────────────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'tokens'">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Permanent API Tokens</h2>
                <p class="text-sm text-gray-500 mt-0.5">Use these tokens to authenticate external partner integrations (prefix: <code class="text-xs bg-gray-100 rounded px-1">vnd_</code>).</p>
            </div>
            @if(auth()->guard('vendor')->user()->is_owner || auth()->guard('vendor')->user()->isManager())
            <button @click="showCreate = true" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Generate Token
            </button>
            @endif
        </div>

        {{-- Token table --}}
        @if($tokens->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 py-12 text-center">
            <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 18.75 8.25Z" /></svg>
            <p class="mt-3 text-sm text-gray-500">No API tokens yet.</p>
        </div>
        @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Prefix</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Last Used</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Created</th>
                        @if(auth()->guard('vendor')->user()->is_owner || auth()->guard('vendor')->user()->isManager())
                        <th class="px-4 py-3"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($tokens as $token)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $token->name }}</td>
                        <td class="px-4 py-3"><code class="text-xs bg-gray-100 rounded px-2 py-0.5 font-mono text-gray-700">{{ $token->token_prefix }}…</code></td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $token->created_at->format('M j, Y') }}</td>
                        @if(auth()->guard('vendor')->user()->is_owner || auth()->guard('vendor')->user()->isManager())
                        <td class="px-4 py-3 text-right">
                            <button @click="confirmRevoke({{ $token->id }}, '{{ e($token->name) }}')" class="text-sm text-red-600 hover:text-red-800 font-medium transition">Revoke</button>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Auth info --}}
        <div class="mt-6 rounded-xl border border-blue-100 bg-blue-50 p-5">
            <h3 class="text-sm font-semibold text-blue-900 mb-2">How to authenticate</h3>
            <p class="text-sm text-blue-800 mb-3">Pass your token as a Bearer token in the <code class="bg-blue-100 rounded px-1">Authorization</code> header:</p>
            <pre class="rounded-lg bg-blue-900 text-blue-100 text-xs p-4 overflow-x-auto">Authorization: Bearer vnd_YOUR_TOKEN_HERE</pre>
            <p class="text-xs text-blue-700 mt-3">Mobile app users authenticate with email/password via <code class="bg-blue-100 rounded px-1">POST /api/partner/v1/auth/login</code> (JWT).</p>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────────────────── --}}
    {{-- TAB: DOCUMENTATION --}}
    {{-- ──────────────────────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'docs'" class="space-y-8">

        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h3 class="font-semibold text-gray-900">Base URL</h3>
            </div>
            <div class="px-5 py-4">
                <code class="text-sm bg-gray-100 rounded px-3 py-2 block">{{ rtrim(config('app.url'), '/') }}/api/partner/v1</code>
                <p class="mt-3 text-sm text-gray-600">All requests must include <code class="bg-gray-100 rounded px-1 text-xs">Accept: application/json</code>.</p>
            </div>
        </div>

        @foreach($docSections as $section)
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden" x-data="{ open: false }">
            <button @click="open = !open" class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition">
                <h3 class="font-semibold text-gray-900">{{ $section['title'] }}</h3>
                <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 text-gray-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <div x-show="open" x-collapse>
                <table class="min-w-full divide-y divide-gray-100 border-t border-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 w-24">Method</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Endpoint</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($section['endpoints'] as $ep)
                        <tr>
                            <td class="px-4 py-2.5">
                                <span class="inline-block rounded px-2 py-0.5 text-xs font-bold font-mono
                                    {{ match($ep['method']) {
                                        'GET'    => 'bg-emerald-100 text-emerald-700',
                                        'POST'   => 'bg-blue-100 text-blue-700',
                                        'PUT'    => 'bg-amber-100 text-amber-700',
                                        'DELETE' => 'bg-red-100 text-red-700',
                                        default  => 'bg-gray-100 text-gray-700',
                                    } }}">{{ $ep['method'] }}</span>
                            </td>
                            <td class="px-4 py-2.5"><code class="text-xs text-gray-700 font-mono">{{ $ep['path'] }}</code></td>
                            <td class="px-4 py-2.5 text-sm text-gray-600">{{ $ep['description'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── MODALS ─────────────────────────────────────────────────────────────── --}}

    {{-- Generate token modal --}}
    <div x-show="showCreate" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="showCreate = false" style="display:none">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Generate API Token</h3>
            <form method="POST" action="{{ route('partner.developer.tokens.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Token Name</label>
                    <input type="text" name="name" required maxlength="100" placeholder="e.g. Production Integration"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
                    <p class="mt-1 text-xs text-gray-500">Give it a descriptive name to identify where it is used.</p>
                </div>
                @error('name')
                <p class="mb-3 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showCreate = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 transition">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition">Generate</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Revoke confirmation modal --}}
    <div x-show="showRevoke" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="showRevoke = false" style="display:none">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Revoke Token</h3>
            <p class="text-sm text-gray-600 mb-5">Are you sure you want to revoke <strong x-text="revokeTokenName"></strong>? Any integration using it will stop working immediately.</p>
            <form :action="`{{ url('partner/developer/tokens') }}/${revokeTokenId}`" method="POST" class="flex justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="showRevoke = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 transition">Cancel</button>
                <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition">Revoke</button>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function developerPage() {
    return {
        tab: '{{ session("new_token") ? "tokens" : "tokens" }}',
        showCreate: false,
        showRevoke: false,
        revokeTokenId: null,
        revokeTokenName: '',
        init() {
            @if(session('new_token'))
            this.tab = 'tokens';
            @endif
        },
        confirmRevoke(id, name) {
            this.revokeTokenId = id;
            this.revokeTokenName = name;
            this.showRevoke = true;
        },
    };
}

function copyToken() {
    const el = document.getElementById('new-token-value');
    if (el) {
        navigator.clipboard.writeText(el.textContent.trim())
            .then(() => alert('Token copied to clipboard!'));
    }
}
</script>
@endpush
