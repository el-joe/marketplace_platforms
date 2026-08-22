@extends('layouts.portal')

@section('title', session('locale', 'ar') === 'ar' ? 'كيف تبدأ' : 'Getting Started')

@section('content')
    @include('portal.partials.getting-started-hero')
    <div class="flex flex-col gap-y-[48px] lg:gap-y-[64px] pb-[48px] lg:pb-[64px] pt-[48px] lg:pt-[64px]">
        @include('portal.partials.store-activation-steps')
        @include('portal.partials.account-setup')
        @include('portal.partials.seller-hub')
        @include('portal.partials.product-listings')
        @include('portal.partials.fulfilment-model')
    </div>
    @include('portal.partials.cta-footer')
@endsection