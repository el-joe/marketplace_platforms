@extends('layouts.adsupport')

@php($totalArticles = $collection->articles->count() + $collection->children->sum(fn ($c) => $c->articles->count()))
@php($isAr = app()->getLocale() === 'ar')

@section('title', $collection->localizedName() . ' | ' . portal_content('adsupport', 'collection', 'page_title_suffix', 'Advertise smarter, grow faster', 'أعلن بذكاء، وانمُ أسرع'))
@section('description', $collection->localizedDescription())

@section('header')
    @include('portal.partials.adsupport-header', ['variant' => 'lite', 'country' => $country])
@endsection

@section('content')
    <nav class="pb-4 text-base" aria-label="{{ $isAr ? 'مسار التنقل' : 'Breadcrumb' }}">
        <ol class="m-0 flex list-none flex-wrap items-baseline gap-2 p-0">
            <li class="flex items-center gap-2">
                <a href="{{ route('portal.adsupport.index', $country) }}" class="text-black no-underline hover:text-[#737373]">{{ portal_content('adsupport', 'collection', 'all_collections', 'All Collections', 'كل المجموعات') }}</a>
                <svg width="6" height="10" viewBox="0 0 6 10" class="block h-2 w-2 fill-[#737373]" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.648862 0.898862C0.316916 1.23081 0.316916 1.769 0.648862 2.10094L3.54782 4.9999L0.648862 7.89886C0.316916 8.23081 0.316917 8.769 0.648862 9.10094C0.980808 9.43289 1.519 9.43289 1.85094 9.10094L5.35094 5.60094C5.68289 5.269 5.68289 4.73081 5.35094 4.39886L1.85094 0.898862C1.519 0.566916 0.980807 0.566916 0.648862 0.898862Z"></path>
                </svg>
            </li>
            <li aria-current="page" class="text-[#737373]">{{ $collection->localizedName() }}</li>
        </ol>
    </nav>

    <div class="flex flex-col gap-10 pt-4">
        <div>
            <div class="mb-5">
                <div class="flex h-14 w-14 items-center justify-center rounded-[10px] bg-[#E0F2FF]">
                    @if($collection->icon)
                        <div class="h-9 w-9 sm:h-10 sm:w-10">
                            <img src="{{ $collection->icon }}" alt="" width="100%" height="100%" loading="lazy">
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex flex-col">
                <h1 class="mb-1 text-2xl font-bold leading-10 text-black">{{ $collection->localizedName() }}</h1>
                @if($collection->localizedDescription())
                    <div class="text-md font-normal leading-normal text-black">
                        <p>{{ $collection->localizedDescription() }}</p>
                    </div>
                @endif
            </div>
            <div class="mt-5">
                <span class="flex text-base text-[#737373]">
                    {{ $totalArticles }} {{ $isAr ? portal_content('adsupport', 'collection', 'article_word', 'article', 'مقالة') : Str::plural('article', $totalArticles) }}
                </span>
            </div>
        </div>

        <div class="flex flex-col gap-5">
            @php($hasAny = $collection->articles->isNotEmpty() || $collection->children->isNotEmpty())

            @if($collection->articles->isNotEmpty())
                <section class="flex flex-col rounded-[10px] border border-solid border-[#e6e6e6] bg-white p-2 sm:p-3">
                    @foreach($collection->articles as $article)
                        <a class="group/article flex flex-row justify-between gap-2 rounded-[10px] px-3 py-2 no-underline transition ease-linear hover:bg-black/5 sm:py-3"
                           href="{{ route('portal.adsupport.articles.show', ['country' => $country, 'article' => $article->slug]) }}">
                            <span class="m-0 text-md text-black group-hover/article:text-black/70">{{ $article->localizedTitle() }}</span>
                            <svg class="block h-4 w-4 shrink-0 text-black ltr:-rotate-90" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </a>
                    @endforeach
                </section>
            @endif

            @foreach($collection->children as $child)
                @continue($child->articles->isEmpty())
                <section class="flex flex-col rounded-[10px] border border-solid border-[#e6e6e6] bg-white p-2 sm:p-3">
                    <div class="p-3 pb-6 text-black">
                        <h2 class="m-0 text-xl font-bold no-underline">{{ $child->localizedName() }}</h2>
                    </div>
                    <hr class="mx-3 mb-2 mt-0 border-0 border-t border-solid border-[#e6e6e6]">

                    @foreach($child->articles as $article)
                        <a class="group/article flex flex-row justify-between gap-2 rounded-[10px] px-3 py-2 no-underline transition ease-linear hover:bg-black/5 sm:py-3"
                           href="{{ route('portal.adsupport.articles.show', ['country' => $country, 'article' => $article->slug]) }}">
                            <span class="m-0 text-md text-black group-hover/article:text-black/70">{{ $article->localizedTitle() }}</span>
                            <svg class="block h-4 w-4 shrink-0 text-black ltr:-rotate-90" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </a>
                    @endforeach
                </section>
            @endforeach

            @if($totalArticles === 0)
                <section class="flex flex-col items-center rounded-[10px] border border-solid border-[#e6e6e6] bg-white p-10 text-center text-[#737373]">
                    {{ portal_content('adsupport', 'collection', 'no_articles', 'Articles in this collection are coming soon.', 'ستتوفر المقالات في هذه المجموعة قريباً.') }}
                </section>
            @endif
        </div>
    </div>
@endsection
