@extends('layouts.advertise')

@section('title', portal_content('advertise-advertisers', 'meta', 'title', 'Popular Ad Solutions | Advertisers - Nawy', 'حلول الإعلانات ذات الشعبية | المعلنين - ناوي'))
@section('description', portal_content('advertise-advertisers', 'meta', 'description', 'Not selling on Nawy, but looking to boost your visibility to large audiences across geographies? Leverage our ad solutions to reach your targeted customers.', 'لا تبيع على ناوي، ولكنك تتطلع إلى تعزيز ظهورك لجماهير فئة كبيرة عبر المناطق الجغرافية؟ استفد من حلولنا الإعلانية للوصول إلى عملائك المستهدفين.'))

@section('content')
    @include('portal.partials.advertisers-hero')
    @include('portal.partials.advertisers-solutions')
@endsection
