@extends('layouts.marketer')

@section('title', __('product_requests.title'))
@section('page-title', __('product_requests.title'))

@section('content')
    @include('shared.product-requests.index', [
        'panel' => 'marketer',
        'localTime' => fn ($date) => country_tz($date, auth('marketer')->user()?->marketer?->country),
    ])
@endsection
