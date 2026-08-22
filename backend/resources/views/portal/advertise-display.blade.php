@extends('layouts.advertise')

@section('title', portal_content('advertise-display', 'meta', 'title', 'Display Ads | noon', 'إعلانات العرض | نون'))
@section('description', portal_content('advertise-display', 'meta', 'description', 'Highlight specific campaigns you want to push, including new launches, clearance items, or seasonal offerings to target audiences likely to be interested.', 'قم بتسليط الضوء على الحملات المحددة التي ترغب في الترويج لها، بما في ذلك عمليات الإطلاق الجديدة أو منتجات التصفية أو العروض الموسمية لاستهداف الجماهير التي من المحتمل أن تكون مهتمة.'))

@section('content')
    @include('portal.partials.display-hero')
    @include('portal.partials.display-quick-guide')
    @include('portal.partials.display-faq')
@endsection
