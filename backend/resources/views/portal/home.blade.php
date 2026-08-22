@extends('layouts.portal')

@section('title', session('locale', 'ar') === 'ar' ? 'بيع على نون' : 'Sell on Noon')

@section('content')
    @include('portal.partials.hero')
    <div class="flex flex-col gap-y-[48px] lg:gap-y-[64px] pb-[48px] lg:pb-[64px] pt-[48px] lg:pt-[64px]">
        @include('portal.partials.why-sell')
        @include('portal.partials.how-it-works')
        @include('portal.partials.fulfillment')
        @include('portal.partials.smart-tools')
        @include('portal.partials.testimonials')
        @include('portal.partials.help')
    </div>
    @include('portal.partials.cta-footer')
@endsection
