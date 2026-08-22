@extends('layouts.portal')

@section('title', session('locale', 'ar') === 'ar' ? 'الشحن والتوصيل' : 'Shipping & Fulfilment')

@section('content')
    @include('portal.partials.fulfillment-hero')
    @include('portal.partials.fulfillment-detail')
    @include('portal.partials.cta-footer')
@endsection