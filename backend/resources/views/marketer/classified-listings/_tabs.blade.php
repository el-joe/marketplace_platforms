<div class="flex items-center justify-between flex-wrap gap-2">
    <div class="flex gap-2">
        @foreach([
            'classified-listings' => 'my_listings',
            'classified-inquiries' => 'received_inquiries',
            'wanted-listings' => 'wanted_listings',
        ] as $r => $k)
            <a href="{{ route('marketer.'.$r.'.index') }}"
               class="px-3 py-1 rounded-full text-xs font-semibold border {{ request()->routeIs('marketer.'.$r.'.*') ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-300 text-gray-600' }}">
                {{ __('marketer.'.$k) }}
            </a>
        @endforeach
    </div>
    <a href="{{ request()->routeIs('marketer.wanted-listings.*') ? route('marketer.wanted-listings.create') : route('marketer.classified-listings.create') }}"
       class="px-4 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500">
        + {{ request()->routeIs('marketer.wanted-listings.*') ? __('marketer.add_wanted_listing') : __('marketer.add_classified_listing') }}
    </a>
</div>
