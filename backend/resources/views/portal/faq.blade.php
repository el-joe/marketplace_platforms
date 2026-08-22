@extends('layouts.portal')

@section('title', session('locale', 'ar') === 'ar' ? 'الأسئلة الشائعة' : 'FAQ')

@section('content')
    @include('portal.partials.faq')
    @include('portal.partials.cta-footer')
@endsection