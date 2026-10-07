@extends('layouts.advertise')

@section('title', portal_content('advertise-brands', 'meta', 'title', 'Popular Ad Solutions | Brands - Nawy', 'حلول الإعلانات ذات الشعبية | العلامات التجارية - ناوي'))
@section('description', portal_content('advertise-brands', 'meta', 'description', 'Boost your brand awareness, reach large audiences, and connect with customers by leveraging Nawy ads strategic products.', 'قم بتعزيز الوعي بعلامتك التجارية، والوصول إلى عملاء أكثر، والتواصل مع العملاء من خلال الاستفادة من المنتجات الإستراتيجية لإعلانات ناوي'))

@section('content')
    @include('portal.partials.brands-hero')
    @include('portal.partials.brands-solutions')
    @include('portal.partials.brands-testimonials')
@endsection
