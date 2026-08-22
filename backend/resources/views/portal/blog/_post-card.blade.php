<article class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden hover:border-gray-700 hover:shadow-xl hover:shadow-black/30 transition-all group flex flex-col">
    @if($post->featured_image_path)
        <a href="{{ route('portal.blog.show', $post->slug) }}" class="block aspect-video overflow-hidden bg-gray-800">
            <img src="{{ Storage::url($post->featured_image_path) }}"
                 alt="{{ $post->{'featured_image_alt_' . $locale} }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                 loading="lazy">
        </a>
    @else
        <div class="aspect-video bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center text-gray-600 text-4xl">📝</div>
    @endif

    <div class="p-5 flex flex-col flex-1">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-xs font-semibold rounded-full px-2.5 py-0.5"
                  style="background-color: {{ $post->category->color_hex ?? '#F59E0B' }}1F; color: {{ $post->category->color_hex ?? '#FBBF24' }}">
                {{ $post->category->{'name_' . $locale} }}
            </span>
            <span class="text-xs text-gray-500">· {{ $post->reading_time_minutes }} {{ __('portal.blog.min_read') }}</span>
        </div>

        <h3 class="font-bold text-white leading-snug line-clamp-2 flex-1 group-hover:text-[#feee00] transition-colors">
            <a href="{{ route('portal.blog.show', $post->slug) }}">
                {{ $post->{'title_' . $locale} }}
            </a>
        </h3>

        <p class="text-sm text-gray-400 mt-2 line-clamp-2 leading-relaxed">
            {{ $post->{'excerpt_' . $locale} }}
        </p>

        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-800">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-full bg-[#feee00]/10 flex items-center justify-center text-[#feee00] text-xs font-bold">
                    {{ mb_substr($post->author->name, 0, 1) }}
                </div>
                <span class="text-xs text-gray-400">{{ $post->author->name }}</span>
            </div>
            <span class="text-xs text-gray-500">
                {{ $post->published_at->format('d M Y') }}
            </span>
        </div>
    </div>
</article>
