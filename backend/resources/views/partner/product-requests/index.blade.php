@extends('layouts.partner')

@section('title', __('product_requests.title'))
@section('page-title', __('product_requests.title'))

@section('content')
    @include('shared.product-requests.index', [
        'panel' => 'partner',
        'localTime' => fn ($date) => vendor_tz($date),
    ])
@endsection
