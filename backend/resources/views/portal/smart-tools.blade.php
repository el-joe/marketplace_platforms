@extends('layouts.portal')

@section('title', session('locale', 'ar') === 'ar' ? 'الأدوات الذكية' : 'Smart Tools')

@section('content')
    @include('portal.partials.smart-tools-hero')
    @include('portal.partials.smart-tools-detail')
    @include('portal.partials.cta-footer')
@endsection