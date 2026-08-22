@extends('layouts.helpcenter')

@php $isAr = app()->getLocale() === 'ar'; @endphp

@section('title', portal_content('helpcenter', 'index', 'page_title', 'noon Seller Help Center', 'مركز مساعدة البائع'))
@section('description', portal_content('helpcenter', 'index', 'page_description', 'noon Seller Help Center', 'مركز مساعدة البائع في نون'))

@section('header')
    @include('portal.partials.helpcenter-header', ['variant' => 'home', 'country' => $country])
@endsection

@section('content')
    <div class="flex flex-col gap-12">

        <div class="grid auto-rows-auto gap-x-4 sm:gap-x-6 gap-y-4 sm:gap-y-6 md:grid-cols-3" role="list">
            @forelse($categories as $category)
                @php($articleCount = $category->totalPublishedArticleCount())
                <a href="{{ route('portal.helpcenter.category.show', ['country' => $country, 'category' => $category->slug]) }}"
                   class="group flex grow overflow-hidden border border-solid border-[#e6e6e6] bg-white no-underline shadow-sm transition ease-linear rounded-[10px] hover:border-orange-400 items-center gap-4 px-6 py-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[10px] bg-orange-50">
                        @if($category->icon)
                            <img class="h-7 w-7" src="{{ $category->icon }}" alt="" width="28" height="28" loading="lazy">
                        @else
                            <svg class="h-7 w-7 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        @endif
                    </div>
                    <div class="flex w-full flex-1 flex-col gap-1 text-black">
                        <div class="-mt-1 mb-0.5 line-clamp-2 text-base font-semibold leading-normal transition ease-linear group-hover:text-orange-600 sm:line-clamp-1">
                            {{ $category->localizedName() }}
                        </div>
                        <div class="flex">
                            <span class="line-clamp-1 flex text-sm text-[#737373]">
                                {{ $articleCount }} {{ $isAr ? portal_content('helpcenter', 'index', 'article_word', 'article', 'مقالة') : Str::plural('article', $articleCount) }}
                            </span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-3 rounded-[10px] border border-dashed border-gray-300 p-10 text-center text-gray-400">
                    {{ portal_content('helpcenter', 'index', 'no_categories', 'No categories yet.', 'لا توجد فئات بعد.') }}
                </div>
            @endforelse
        </div>

        @if($featuredArticle)
            <section class="mb-14 flex w-full flex-col items-center bg-white py-14 text-black rounded-[10px] border border-solid border-[#e6e6e6]">
                <div class="flex flex-col items-center px-6 sm:w-[482px] sm:px-0">
                    <header class="text-center text-2xl sm:text-[28px] font-semibold">{{ $featuredArticle->localizedTitle() }}</header>
                    <div class="mt-2 whitespace-pre-wrap text-center text-gray-500">{{ $featuredArticle->localizedExcerpt() }}</div>
                    <a href="{{ route('portal.helpcenter.article.show', ['country' => $country, 'article' => $featuredArticle->slug]) }}"
                       class="mt-6 rounded-lg bg-orange-500 px-4 py-2 font-semibold text-white no-underline hover:bg-orange-600">
                        {{ portal_content('helpcenter', 'index', 'learn_more', 'Learn more', 'اطّلع أكثر') }}
                    </a>
                </div>
            </section>
        @endif
    </div>
@endsection
