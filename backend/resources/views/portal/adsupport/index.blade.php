@extends('layouts.adsupport')

@php $isAr = app()->getLocale() === 'ar'; @endphp

@section('title', portal_content('adsupport', 'index', 'page_title', 'Home | Advertise smarter, grow faster', 'الرئيسية | أعلن بذكاء، وانمُ أسرع'))
@section('description', portal_content('adsupport', 'index', 'page_description', 'Advertise smarter, grow faster', 'أعلن بذكاء، وانمُ أسرع'))

@section('header')
    @include('portal.partials.adsupport-header', ['variant' => 'home', 'country' => $country])
@endsection

@section('content')
    <div class="flex flex-col gap-12">

        <div class="grid auto-rows-auto gap-x-4 sm:gap-x-6 gap-y-4 sm:gap-y-6 md:grid-cols-3" role="list">
            @foreach($collections as $collection)
                @php($articleCount = $collection->totalPublishedArticleCount())
                <a href="{{ route('portal.adsupport.collections.show', ['country' => $country, 'collection' => $collection->slug]) }}"
                   class="group flex grow overflow-hidden border border-solid border-[#e6e6e6] bg-white no-underline shadow-sm transition ease-linear rounded-[10px] hover:border-black/60 items-center gap-4 px-6 py-4">
                    <div class="flex h-15 w-15 shrink-0 items-center justify-center rounded-[10px] bg-[#E0F2FF]">
                        @if($collection->icon)
                            <img class="h-7 w-7" src="{{ $collection->icon }}" alt="" width="28" height="28" loading="lazy">
                        @endif
                    </div>
                    <div class="flex w-full flex-1 flex-col gap-1 text-black">
                        <div class="-mt-1 mb-0.5 line-clamp-2 text-md font-semibold leading-normal transition ease-linear group-hover:text-black/70 sm:line-clamp-1">
                            {{ $collection->localizedName() }}
                        </div>
                        <div class="flex">
                            <span class="line-clamp-1 flex text-base text-[#737373]">
                                {{ $articleCount }} {{ $isAr ? portal_content('adsupport', 'index', 'article_word', 'article', 'مقالة') : Str::plural('article', $articleCount) }}
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if($featuredArticle)
            <section class="mb-14 flex w-full flex-col items-center bg-white py-14 text-black rounded-[10px] border border-solid border-[#e6e6e6]">
                <div class="flex flex-col items-center px-6 sm:w-[482px] sm:px-0">
                    <header class="text-center text-[28px] font-medium">{{ portal_content('adsupport', 'index', 'featured_heading', 'New to ads?', 'جديد على الإعلانات؟') }}</header>
                    <div class="mt-2 whitespace-pre-wrap text-center">{{ $featuredArticle->localizedExcerpt() }}</div>
                    <a href="{{ route('portal.adsupport.articles.show', ['country' => $country, 'article' => $featuredArticle->slug]) }}"
                       class="mt-6 rounded-lg bg-[#333333] px-[14px] py-2 font-semibold text-white no-underline hover:opacity-90">
                        {{ portal_content('adsupport', 'index', 'learn_more', 'Learn more', 'اطّلع أكثر') }}
                    </a>
                </div>
            </section>
        @endif
    </div>
@endsection
