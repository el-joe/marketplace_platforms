@extends('layouts.portal')

@section('title', portal_content('smart-tools', 'meta', 'title', 'Smart Tools', 'الأدوات الذكية'))

@section('content')
    @include('portal.partials.smart-tools-hero')
    @include('portal.partials.smart-tools-detail')
    @include('portal.partials.cta-footer')
@endsection