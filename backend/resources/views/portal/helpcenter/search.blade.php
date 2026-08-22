@extends('layouts.helpcenter')

@php $isAr = app()->getLocale() === 'ar'; @endphp

@section('title', portal_content('helpcenter', 'search', 'results_word', 'Search results', 'نتائج البحث') . ' | ' . portal_content('helpcenter', 'search', 'page_title', 'noon Seller Help Center', 'مركز مساعدة البائع'))

@section('header')
    @include('portal.partials.helpcenter-header', ['variant' => 'home', 'country' => $country])
@endsection

@section('content')
    <div class="flex flex-col gap-6">
        <h1 class="text-xl font-semibold text-gray-800">
            {{ portal_content('helpcenter', 'search', 'results_for', 'Search results for', 'نتائج البحث عن') }} "{{ $q }}"
            <span class="text-gray-400 font-normal text-base">({{ $articles->count() }})</span>
        </h1>

        @if($articles->isNotEmpty())
            <section class="flex flex-col rounded-[10px] border border-solid border-[#e6e6e6] bg-white p-2 sm:p-3">
                @foreach($articles as $article)
                    <a class="group/article flex flex-row justify-between gap-2 rounded-[10px] px-3 py-3 no-underline transition ease-linear hover:bg-orange-50"
                       href="{{ route('portal.helpcenter.article.show', ['country' => $country, 'article' => $article->slug]) }}">
                        <div>
                            <span class="m-0 text-base text-black group-hover/article:text-orange-600 font-medium">{{ $article->localizedTitle() }}</span>
                            @if($article->localizedExcerpt())
                                <p class="m-0 mt-1 text-sm text-gray-500 line-clamp-1">{{ $article->localizedExcerpt() }}</p>
                            @endif
                        </div>
                        <svg class="block h-4 w-4 shrink-0 text-black {{ $isAr ? 'rotate-90' : '-rotate-90' }}" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                    </a>
                @endforeach
            </section>
        @else
            <section class="flex flex-col items-center rounded-[10px] border border-solid border-[#e6e6e6] bg-white p-10 text-center text-[#737373]">
                {{ portal_content('helpcenter', 'search', 'no_results', 'No matching articles found.', 'لم يتم العثور على نتائج مطابقة.') }}
            </section>
        @endif
    </div>
@endsection
