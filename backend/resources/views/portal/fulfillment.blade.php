@extends('layouts.portal')

@section('title', portal_content('fulfillment', 'meta', 'title', 'Shipping & Fulfilment', 'الشحن والتوصيل'))

@section('content')
    @include('portal.partials.fulfillment-hero')
    @include('portal.partials.fulfillment-detail')
    @include('portal.partials.cta-footer')
@endsection