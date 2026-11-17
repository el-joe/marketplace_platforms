{{-- business_* placeholders filled into static pages from Settings → Business Info (App\Support\BusinessInfo). --}}
<dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs">
    @foreach (\App\Support\BusinessInfo::tokens() as $token => $tokenLabel)
        <div class="flex flex-col">
            <dt><code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-gray-700 select-all" dir="ltr">{{ str_repeat('{', 2).$token.str_repeat('}', 2) }}</code></dt>
            <dd class="mt-0.5 text-gray-500">{{ $tokenLabel }}</dd>
        </div>
    @endforeach
</dl>
