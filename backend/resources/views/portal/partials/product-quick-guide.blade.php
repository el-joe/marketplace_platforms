@php $isAr = session('locale', 'ar') === 'ar'; @endphp

<section class="bg-white">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 pb-12 lg:pb-16">
        <h2 class="text-2xl sm:text-3xl lg:text-[34px] font-extrabold text-gray-900 text-center mb-6 lg:mb-10">
            {{ portal_content('advertise-product', 'quick_guide', 'title', 'Quick Start Guide', 'دليل البدء السريع') }}
        </h2>

        <div class="relative rounded-2xl overflow-hidden bg-gray-100 aspect-video max-w-[960px] mx-auto shadow-sm"
             x-data="{
                playing: false,
                muted: false,
                progress: 0,
                toggle() {
                    if (this.playing) { this.$refs.video.pause(); } else { this.$refs.video.play(); }
                },
                seek(delta) {
                    const v = this.$refs.video;
                    v.currentTime = Math.min(Math.max(v.currentTime + delta, 0), v.duration || 0);
                },
                onTimeUpdate() {
                    const v = this.$refs.video;
                    this.progress = v.duration ? (v.currentTime / v.duration) * 100 : 0;
                },
                toggleMute() {
                    this.muted = !this.muted;
                    this.$refs.video.muted = this.muted;
                }
             }">

            <video x-ref="video" class="absolute inset-0 w-full h-full object-contain bg-black" preload="auto" playsinline
                   @play="playing = true" @pause="playing = false" @ended="playing = false"
                   @timeupdate="onTimeUpdate" @click="toggle">
                <source src="https://advertise.noon.com/videos/product-ads2.mp4" type="video/mp4">
            </video>

            {{-- Skip back 10s --}}
            <button x-show="playing" x-cloak @click.stop="seek(-10)"
                    class="absolute {{ $isAr ? 'end-1/2 me-16 sm:me-24' : 'start-1/2 ms-16 sm:ms-24' }} top-1/2 -translate-y-1/2
                           w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center opacity-90 hover:opacity-100 transition-opacity">
                <img src="https://advertise.noon.com/icons/rewind10.svg" alt="{{ portal_content('advertise-product', 'quick_guide', 'rewind_alt', 'Skip backward 10 seconds', 'إرجاع 10 ثوانٍ') }}" class="w-full h-full">
            </button>

            {{-- Center play / pause --}}
            <button @click="toggle" class="absolute inset-0 flex items-center justify-center" aria-label="{{ portal_content('advertise-product', 'quick_guide', 'play_pause_label', 'Play / Pause', 'تشغيل / إيقاف') }}">
                <img x-show="!playing" src="https://advertise.noon.com/icons/play.png" alt="{{ portal_content('advertise-product', 'quick_guide', 'play_alt', 'Play', 'تشغيل') }}"
                     class="w-14 h-14 sm:w-16 sm:h-16">
            </button>

            {{-- Skip forward 10s --}}
            <button x-show="playing" x-cloak @click.stop="seek(10)"
                    class="absolute {{ $isAr ? 'start-1/2 ms-16 sm:ms-24' : 'end-1/2 me-16 sm:me-24' }} top-1/2 -translate-y-1/2
                           w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center opacity-90 hover:opacity-100 transition-opacity">
                <img src="https://advertise.noon.com/icons/forward10.svg" alt="{{ portal_content('advertise-product', 'quick_guide', 'forward_alt', 'Skip forward 10 seconds', 'تقديم 10 ثوانٍ') }}" class="w-full h-full">
            </button>

            {{-- Bottom bar: mute + progress --}}
            <div class="absolute inset-x-0 bottom-0 flex items-center gap-3 px-3 sm:px-4 py-3 bg-gradient-to-t from-black/60 to-transparent">
                <button @click.stop="toggleMute" class="text-white shrink-0" aria-label="{{ portal_content('advertise-product', 'quick_guide', 'mute_label', 'Mute', 'كتم الصوت') }}">
                    <svg x-show="!muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                        <path d="M3 10v4h4l5 5V5L7 10H3z" />
                        <path d="M16.5 12c0-1.77-.77-3.29-2-4.34v8.68a5.98 5.98 0 0 0 2-4.34zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z" />
                    </svg>
                    <svg x-show="muted" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                        <path d="M12 4 9.91 6.09 12 8.18V4zM4.27 3 3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06a8.99 8.99 0 0 0 3.69-1.81L19.73 21 21 19.73 4.27 3zM19 12c0 .94-.2 1.82-.54 2.64l1.51 1.51A8.796 8.796 0 0 0 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zm-4.02.17c0-.06.02-.11.02-.17 0-1.77-1-3.29-2.5-4.03v1.79l2.48 2.41z" />
                    </svg>
                </button>
                <div class="flex-1 h-1 rounded-full bg-white/30 overflow-hidden">
                    <div class="h-full bg-[#feee00]" :style="`width: ${progress}%`"></div>
                </div>
            </div>
        </div>
    </div>
</section>
