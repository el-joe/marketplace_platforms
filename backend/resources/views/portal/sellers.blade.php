@extends('layouts.advertise')

@section('title', portal_content('sellers', 'meta', 'title', 'Popular Ad Solutions | Sellers - Nawy', 'حلول الإعلانات ذات الشعبية | البائعين - ناوي'))
@section('description', portal_content('sellers', 'meta', 'description', 'Join thousands of sellers leveraging our ad solutions to reach their marketing and sales objectives across locations and irrespective of their budgets.', 'انضم إلى آلاف البائعين الذين يستفيدون من حلولنا الإعلانية لتحقيق أهدافهم التسويقية والمبيعاتية عبر المواقع بغض النظر عن ميزانياتهم.'))

@section('content')
    @include('portal.partials.sellers-hero')
    @include('portal.partials.sellers-solutions')
    @include('portal.partials.sellers-testimonials')
@endsection
