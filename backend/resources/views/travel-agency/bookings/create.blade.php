@extends('layouts.travel-agency')

@section('title', __('travel.bookings.new_booking'))

@section('content')
<div class="max-w-2xl space-y-6">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ $package ? route('travel-agency.packages.show', $package) : route('travel-agency.bookings.index') }}"
           class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-2xl font-black text-gray-900">{{ __('travel.bookings.new_booking') }}</h1>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 space-y-1">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if(session('prefill_inquiry'))
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-700">
        {{ __('travel.bookings.prefill_inquiry_message') }}
    </div>
    @endif

    @if($packages->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center space-y-3">
            <p class="text-sm text-gray-500">{{ __('travel.bookings.no_active_packages') }}</p>
            <a href="{{ route('travel-agency.packages.create') }}"
               class="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors">
                {{ __('travel.packages.create_package') }}
            </a>
        </div>
    @else
    <form method="POST" action="{{ route('travel-agency.bookings.store') }}" class="space-y-6">
        @csrf
        @if(old('from_inquiry'))
        <input type="hidden" name="from_inquiry" value="{{ old('from_inquiry') }}">
        @endif

        {{-- Package selection --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <h3 class="font-semibold text-gray-800">{{ __('travel.bookings.package') }}</h3>
            <div>
                <label class="block text-sm text-gray-600 mb-1">
                    {{ __('travel.bookings.select_package') }} <span class="text-red-500">*</span>
                </label>
                <select name="travel_package_id" id="packageSelect" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none @error('travel_package_id') border-red-400 @enderror">
                    <option value="">{{ __('travel.bookings.select_package_placeholder') }}</option>
                    @foreach($packages as $pkg)
                        <option value="{{ $pkg->id }}"
                            data-title="{{ $pkg->title_ar ?: $pkg->title_en }}"
                            data-price="{{ $pkg->price }}"
                            data-currency="{{ $pkg->currency }}"
                            data-seats-remaining="{{ $pkg->seatsRemaining() === null ? '' : $pkg->seatsRemaining() }}"
                            data-departure="{{ $pkg->departure_date->format('d M Y') }}"
                            data-return="{{ $pkg->return_date->format('d M Y') }}"
                            {{ (string) old('travel_package_id', $package?->id) === (string) $pkg->id ? 'selected' : '' }}>
                            {{ $pkg->title_ar ?: $pkg->title_en }}
                        </option>
                    @endforeach
                </select>
                @error('travel_package_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Package summary card --}}
        <div id="packageSummaryCard" class="bg-blue-50 border border-blue-200 rounded-xl p-5 space-y-2 text-sm hidden">
            <h2 id="packageSummaryTitle" class="font-bold text-blue-900 text-base"></h2>
            <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-blue-800">
                <div><span class="text-blue-500">{{ __('travel.packages.departure_date') }}:</span> <span id="packageSummaryDeparture"></span></div>
                <div><span class="text-blue-500">{{ __('travel.packages.return_date') }}:</span> <span id="packageSummaryReturn"></span></div>
                <div><span class="text-blue-500">{{ __('travel.packages.price_per_person') }}:</span> <strong id="packageSummaryPrice"></strong></div>
                <div>
                    <span class="text-blue-500">{{ __('travel.packages.available_seats') }}:</span>
                    <strong id="seatsRemainingDisplay"></strong>
                </div>
            </div>
        </div>

        {{-- Customer mode toggle --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-800">{{ __('travel.bookings.customer_data') }}</h3>

            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="customer_mode" value="existing"
                           class="text-blue-600" {{ old('customer_mode', 'existing') === 'existing' ? 'checked' : '' }}
                           onchange="toggleCustomerMode('existing')">
                    <span class="text-sm font-medium text-gray-700">{{ __('travel.bookings.existing_customer') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="customer_mode" value="new"
                           class="text-blue-600" {{ old('customer_mode') === 'new' ? 'checked' : '' }}
                           onchange="toggleCustomerMode('new')">
                    <span class="text-sm font-medium text-gray-700">{{ __('travel.bookings.new_customer') }}</span>
                </label>
            </div>

            {{-- Existing customer search --}}
            <div id="existingCustomerSection" class="{{ old('customer_mode') === 'new' ? 'hidden' : '' }}">
                <label class="block text-sm text-gray-600 mb-1">{{ __('travel.bookings.search_customer') }}</label>
                <div class="relative">
                    <input type="text" id="customerSearchInput"
                           placeholder="{{ __('travel.bookings.write_to_search') }}"
                           autocomplete="off"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">
                    <div id="customerSearchResults"
                         class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 hidden max-h-52 overflow-y-auto"></div>
                </div>
                <input type="hidden" name="customer_id" id="customerIdInput" value="{{ old('customer_id') }}">
                <p id="selectedCustomerLabel" class="mt-1.5 text-xs text-emerald-600 font-medium"></p>
                @error('customer_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- New customer mini-form --}}
            <div id="newCustomerSection" class="{{ old('customer_mode') !== 'new' ? 'hidden' : '' }} space-y-3">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('travel.bookings.full_name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="new_name" value="{{ old('new_name') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none @error('new_name') border-red-400 @enderror">
                    @error('new_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('travel.bookings.phone') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="new_phone" value="{{ old('new_phone') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none @error('new_phone') border-red-400 @enderror">
                    @error('new_phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('travel.bookings.email') }} <span class="text-red-500">*</span></label>
                    <input type="email" name="new_email" value="{{ old('new_email') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none @error('new_email') border-red-400 @enderror">
                    @error('new_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <p class="text-xs text-gray-400">{{ __('travel.bookings.password_reset_message') }}</p>
            </div>
        </div>

        {{-- Travelers count --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <h3 class="font-semibold text-gray-800">{{ __('travel.bookings.booking_details') }}</h3>

            <div>
                <label class="block text-sm text-gray-600 mb-1">
                    {{ __('travel.bookings.travelers_count') }} <span class="text-red-500">*</span>
                    <span id="maximumTravelersHint" class="text-gray-400 font-normal hidden">
                        ({{ __('travel.bookings.maximum_travelers') }}: <span id="maximumTravelersValue"></span>)
                    </span>
                </label>
                <input type="number" name="travelers_count" id="travelersCountInput"
                       value="{{ old('travelers_count', 1) }}"
                       min="1"
                       class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none @error('travelers_count') border-red-400 @enderror">
                @error('travelers_count') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                <span class="text-sm text-gray-500">{{ __('travel.bookings.total_price') }}</span>
                <span id="totalPriceDisplay" class="text-lg font-black text-gray-900">—</span>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" name="action" value="create_booking"
                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors">
                {{ __('travel.bookings.create_booking') }}
            </button>
            <a href="{{ $package ? route('travel-agency.packages.show', $package) : route('travel-agency.bookings.index') }}"
               class="px-6 py-2.5 border border-gray-300 text-sm text-gray-600 rounded-lg hover:bg-gray-50">
                {{ __('travel.bookings.cancel') }}
            </a>
        </div>
    </form>
    @endif
</div>

@if($packages->isNotEmpty())
<script>
    const searchUrl = @json(route('travel-agency.bookings.customer-search'));
    const unlimitedLabel = @json(__('travel.bookings.unlimited'));
    const noResultsLabel = @json(__('travel.bookings.no_results'));

    function toggleCustomerMode(mode) {
        document.getElementById('existingCustomerSection').classList.toggle('hidden', mode !== 'existing');
        document.getElementById('newCustomerSection').classList.toggle('hidden', mode !== 'new');
    }

    // Package selection → summary card + travelers constraints
    const packageSelect       = document.getElementById('packageSelect');
    const summaryCard         = document.getElementById('packageSummaryCard');
    const summaryTitle        = document.getElementById('packageSummaryTitle');
    const summaryDeparture    = document.getElementById('packageSummaryDeparture');
    const summaryReturn       = document.getElementById('packageSummaryReturn');
    const summaryPrice        = document.getElementById('packageSummaryPrice');
    const seatsRemainingDisplay = document.getElementById('seatsRemainingDisplay');
    const maxTravelersHint    = document.getElementById('maximumTravelersHint');
    const maxTravelersValue   = document.getElementById('maximumTravelersValue');
    const travelersInput      = document.getElementById('travelersCountInput');
    const totalDisplay        = document.getElementById('totalPriceDisplay');

    function selectedOption() {
        return packageSelect.options[packageSelect.selectedIndex];
    }

    function updateTotal() {
        const opt = selectedOption();
        if (!opt || !opt.value) {
            totalDisplay.textContent = '—';
            return;
        }
        const priceCents = parseInt(opt.dataset.priceCents, 10) || 0;
        const currency    = opt.dataset.currency;
        const count       = parseInt(travelersInput.value) || 1;
        const total       = (priceCents * count).toFixed(2);
        totalDisplay.textContent = currency + ' ' + parseFloat(total).toLocaleString('en', {minimumFractionDigits: 2});
    }

    function updateFromSelection() {
        const opt = selectedOption();

        if (!opt || !opt.value) {
            summaryCard.classList.add('hidden');
            maxTravelersHint.classList.add('hidden');
            travelersInput.removeAttribute('max');
            updateTotal();
            return;
        }

        summaryTitle.textContent = opt.dataset.title;
        summaryDeparture.textContent = opt.dataset.departure;
        summaryReturn.textContent = opt.dataset.return;
        summaryPrice.textContent = opt.dataset.currency + ' ' + parseFloat(opt.dataset.priceCents).toLocaleString('en', {minimumFractionDigits: 2});

        const seatsRemaining = opt.dataset.seatsRemaining;
        if (seatsRemaining === '') {
            seatsRemainingDisplay.textContent = unlimitedLabel;
            maxTravelersHint.classList.add('hidden');
            travelersInput.removeAttribute('max');
        } else {
            seatsRemainingDisplay.textContent = seatsRemaining;
            maxTravelersValue.textContent = seatsRemaining;
            maxTravelersHint.classList.remove('hidden');
            travelersInput.max = seatsRemaining;
        }

        summaryCard.classList.remove('hidden');
        updateTotal();
    }

    packageSelect.addEventListener('change', updateFromSelection);
    travelersInput.addEventListener('input', updateTotal);
    updateFromSelection();

    // Customer search
    const searchInput    = document.getElementById('customerSearchInput');
    const resultsBox     = document.getElementById('customerSearchResults');
    const customerIdInput = document.getElementById('customerIdInput');
    const selectedLabel  = document.getElementById('selectedCustomerLabel');
    let searchTimer;

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        const q = this.value.trim();

        if (q.length < 2) {
            resultsBox.classList.add('hidden');
            return;
        }

        searchTimer = setTimeout(async () => {
            try {
                const res  = await fetch(searchUrl + '?q=' + encodeURIComponent(q));
                const data = await res.json();

                if (!data.length) {
                    resultsBox.innerHTML = `<p class="px-3 py-2 text-xs text-gray-400">${noResultsLabel}</p>`;
                } else {
                    resultsBox.innerHTML = data.map(c => `
                        <div class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm"
                             data-id="${c.id}" data-label="${c.name} — ${c.email || c.phone || ''}">
                            <span class="font-medium text-gray-800">${c.name}</span>
                            <span class="text-gray-400 text-xs ml-2">${c.email || ''} ${c.phone ? '| '+c.phone : ''}</span>
                        </div>
                    `).join('');
                }
                resultsBox.classList.remove('hidden');
            } catch (e) {
                // ignore network errors silently
            }
        }, 280);
    });

    resultsBox.addEventListener('click', function (e) {
        const row = e.target.closest('[data-id]');
        if (!row) return;
        customerIdInput.value = row.dataset.id;
        searchInput.value = row.dataset.label.split(' — ')[0];
        selectedLabel.textContent = '✓ ' + row.dataset.label;
        resultsBox.classList.add('hidden');
    });

    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
            resultsBox.classList.add('hidden');
        }
    });
</script>
@endif
@endsection
