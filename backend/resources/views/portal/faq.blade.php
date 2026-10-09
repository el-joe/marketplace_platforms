@extends('layouts.portal')

@section('title', portal_content('faq', 'meta', 'title', 'FAQ', 'الأسئلة الشائعة'))

@section('content')
    @include('portal.partials.faq')
    @include('portal.partials.cta-footer')
@endsection