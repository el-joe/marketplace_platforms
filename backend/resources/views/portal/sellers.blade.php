@extends('layouts.advertise')

@section('title', session('locale', 'ar') === 'ar' ? 'حلول الإعلانات ذات الشعبية | البائعين - نون' : 'Popular Ad Solutions | Sellers - noon')
@section('description', session('locale', 'ar') === 'ar'
    ? 'انضم إلى آلاف البائعين الذين يستفيدون من حلولنا الإعلانية لتحقيق أهدافهم التسويقية والمبيعاتية عبر المواقع بغض النظر عن ميزانياتهم.'
    : 'Join thousands of sellers leveraging our ad solutions to reach their marketing and sales objectives across locations and irrespective of their budgets.')

@section('content')
    @include('portal.partials.sellers-hero')
    @include('portal.partials.sellers-solutions')
    @include('portal.partials.sellers-testimonials')
@endsection
