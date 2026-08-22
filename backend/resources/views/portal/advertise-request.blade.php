@extends('layouts.advertise')

@section('title', portal_content('advertise-request', 'meta', 'title', "Let's start a conversation | Contact us - noon", 'لنبدأ محادثة | اتصل بنا - نون'))
@section('description', portal_content('advertise-request', 'meta', 'description', 'Leverage our ad solutions today to reach your marketing or sales objectives across locations and irrespective of your budget.', 'استفد من حلولنا الإعلانية اليوم للوصول إلى أهدافك التسويقية أو البيعية عبر المواقع بغض النظر عن ميزانيتك.'))

@section('content')
    @include('portal.partials.advertise-request-form')
@endsection
