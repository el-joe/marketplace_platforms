@php
    $isAr = session('locale', 'ar') === 'ar';

    $testimonials = [
        [
            'logo' => 'https://advertise.noon.com/images/samsungLogo.png',
            'quote' => portal_content('advertise-brands', 'testimonial_1', 'quote', "'Our partnership with noon during Yellow Friday has been a remarkable success, showcasing our growth and innovation. We are proud to exclusively offer our 2nd generation Freestyle Projector through noon, and we anticipate outstanding results. Together, we are leading the way in delivering cutting-edge technology to our valued customers.'", "'لقد حققت شراكتنا مع نون خلال يوم الجمعة الصفراء نجاحًا ملحوظًا، حيث أظهرت نمونا وابتكارنا. نحن فخورون بأن نقدم حصريًا الجيل الثاني من جهاز العرض فري ستايل في نون، ونتوقع نتائج رائعة. معًا، نحن نقود الطريق في تقديم التكنولوجيا المتطورة لعملائنا الكرام.'"),
            'name' => portal_content('advertise-brands', 'testimonial_1', 'name', 'Ahmed Sultan', 'احمد سلطان'),
            'position' => portal_content('advertise-brands', 'testimonial_1', 'position', 'Product Marketing Manager, Samsung', 'مدير تسويق المنتجات سامسونج'),
        ],
        [
            'logo' => 'https://advertise.noon.com/images/lorealLogo.png',
            'quote' => portal_content('advertise-brands', 'testimonial_2', 'quote', "'Since integrating noon's two new data dashboards in 2022 and 2023, our data sharing capabilities have soared, leading to enhanced ROI across all account media spends. noon's innovative solutions have truly optimized our partnership and elevated our strategic decisions to unprecedented levels of success'", "'منذ دمج لوحتي بيانات نون الجديدتين في عامي 2022 و 2023، تجاوزت قدراتنا في مشاركة البيانات، مما أدى إلى تعزيز عائد الاستثمار في جميع النفقات الإعلامية للحساب. لقد قامت حلول نون المبتكرة حقًا بتحسين شراكتنا ورفع قراراتنا الاستراتيجية إلى مستويات نجاح غير مسبوقة.'"),
            'name' => portal_content('advertise-brands', 'testimonial_2', 'name', 'Dania Elhussein', 'دانيا الحسين'),
            'position' => portal_content('advertise-brands', 'testimonial_2', 'position', "E-Com Manager, L'Oreal LDB Division", "مدير التجارة الإلكترونية، قسم L'Oreal LDB"),
        ],
        [
            'logo' => 'https://advertise.noon.com/images/motorola.png',
            'quote' => portal_content('advertise-brands', 'testimonial_3', 'quote', "'We partnered with noon to lead our advertising efforts for the launch of our flagship device. The campaign not only gave us significant visibility offline & onsite but helped cement our partnership by reinforcing noon as a key destination for Motorola products. The results are evident in the traffic generated, word of mouth & record sales number that we have witnessed so far'", "'تعاونا مع نون لقيادة إعلاناتنا لإطلاق جهازنا الرئيسي. لم تمنح الحملة لنا فقط رؤية ملحوظة في الموقع وخارجه، بل ساعدت أيضًا في ترسيخ شراكتنا من خلال تعزيز نون كوجهة أساسية لمنتجات موتورولا. كانت النتائج واضحة في رفع حركة المرور، والترويج الشفوي، وأرقام المبيعات القياسية التي شهدناها حتى الآن.'"),
            'name' => portal_content('advertise-brands', 'testimonial_3', 'name', 'Vinayak Shenoy', 'فيناياك شينوي'),
            'position' => portal_content('advertise-brands', 'testimonial_3', 'position', 'Marketing Director, Motorola Mobiles', 'مدير التسويق بشركة موتورولا للهواتف المحمولة'),
        ],
    ];
@endphp

<section class="bg-gray-50 py-12 lg:py-16">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-center text-yellow-500 font-black text-xs sm:text-sm uppercase tracking-wider mb-8 lg:mb-10">
            {{ portal_content('advertise-brands', 'testimonials', 'eyebrow', 'Hear from our satisfied customers', 'استمع إلى آراء عملائنا') }}
        </p>

        <div x-data="{ active: 0, count: {{ count($testimonials) }} }" class="relative">
            <div class="overflow-hidden">
                <div class="flex transition-transform duration-500 ease-out"
                     :style="`transform: translateX(${ {{ $isAr ? '1' : '-1' }} * active * 100}%)`">
                    @foreach($testimonials as $t)
                        <div class="w-full shrink-0 px-2 sm:px-10 text-center">
                            <img src="{{ $t['logo'] }}" alt="{{ $t['name'] }}"
                                 loading="lazy" class="h-8 mx-auto mb-6 object-contain">
                            <p class="text-gray-700 text-base sm:text-lg max-w-[70ch] mx-auto leading-relaxed text-pretty">
                                {{ $t['quote'] }}
                            </p>
                            <p class="mt-6 font-extrabold text-gray-900">{{ $t['name'] }}</p>
                            <p class="text-sm text-gray-500">{{ $t['position'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Prev / next arrows --}}
            <button type="button" @click="active = (active - 1 + count) % count"
                    class="hidden sm:flex absolute {{ $isAr ? '-right-2 lg:-right-6' : '-left-2 lg:-left-6' }} top-1/2 -translate-y-1/2
                           w-9 h-9 items-center justify-center rounded-full bg-white shadow hover:bg-gray-100 text-gray-600"
                    aria-label="{{ portal_content('advertise-brands', 'testimonials', 'prev_label', 'Previous', 'السابق') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" class="{{ $isAr ? '-scale-x-100' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.75 8.25 7 12m0 0 3.75 3.75M7 12h10" />
                </svg>
            </button>
            <button type="button" @click="active = (active + 1) % count"
                    class="hidden sm:flex absolute {{ $isAr ? '-left-2 lg:-left-6' : '-right-2 lg:-right-6' }} top-1/2 -translate-y-1/2
                           w-9 h-9 items-center justify-center rounded-full bg-white shadow hover:bg-gray-100 text-gray-600"
                    aria-label="{{ portal_content('advertise-brands', 'testimonials', 'next_label', 'Next', 'التالي') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" class="{{ $isAr ? '-scale-x-100' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.25 8.25 17 12m0 0-3.75 3.75M17 12H7" />
                </svg>
            </button>

            {{-- Dots --}}
            <div class="flex justify-center gap-2 mt-8">
                @php($slideLabel = portal_content('advertise-brands', 'testimonials', 'slide_label', 'Slide', 'الشريحة'))
                @foreach($testimonials as $i => $t)
                    <button type="button" @click="active = {{ $i }}"
                            :class="active === {{ $i }} ? 'w-6 bg-gray-900' : 'w-4 bg-gray-300'"
                            class="h-1.5 rounded-full transition-all"
                            aria-label="{{ $slideLabel }} {{ $i + 1 }}"></button>
                @endforeach
            </div>
        </div>
    </div>
</section>
