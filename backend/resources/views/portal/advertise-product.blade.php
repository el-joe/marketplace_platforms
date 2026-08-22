@extends('layouts.advertise')

@section('title', portal_content('advertise-product', 'meta', 'title', 'Product Ads | noon', 'إعلانات المنتجات | نون'))
@section('description', portal_content('advertise-product', 'meta', 'description', 'Amplify your products visibility on the lower funnel with targeted ads that reach a larger customer base and enable growth.', 'قم بتعزيز رؤية منتجاتك على مسار التحويل السفلي من خلال الإعلانات المستهدفة التي تصل إلى قاعدة عملاء أكبر وتمكّن النمو.'))

@section('content')
    @include('portal.partials.product-hero')
    @include('portal.partials.product-quick-guide')
    @include('portal.partials.product-faq')
@endsection
