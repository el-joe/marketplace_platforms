@extends('layouts.partner')

@section('title', __('product_requests.singular') . ' · ' . $productRequest->request_number)
@section('page-title', __('product_requests.title'))

@section('content')
    @include('shared.product-requests.show', [
        'panel' => 'partner',
        'localTime' => fn ($date) => vendor_tz($date),
    ])
@endsection
