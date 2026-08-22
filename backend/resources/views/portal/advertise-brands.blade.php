@extends('layouts.advertise')

@section('title', portal_content('advertise-brands', 'meta', 'title', 'Popular Ad Solutions | Brands - noon', 'حلول الإعلانات ذات الشعبية | العلامات التجارية - نون'))
@section('description', portal_content('advertise-brands', 'meta', 'description', 'Boost your brand awareness, reach large audiences, and connect with customers by leveraging noon ads strategic products.', 'قم بتعزيز الوعي بعلامتك التجارية، والوصول إلى عملاء أكثر، والتواصل مع العملاء من خلال الاستفادة من المنتجات الإستراتيجية لإعلانات نون'))

@section('content')
    @include('portal.partials.brands-hero')
    @include('portal.partials.brands-solutions')
    @include('portal.partials.brands-testimonials')
@endsection
