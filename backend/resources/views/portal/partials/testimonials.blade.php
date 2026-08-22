@php $isAr = session('locale', 'ar') === 'ar'; @endphp

<section>
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-[#feee00] font-bold text-[28px] lg:text-[36px] mb-8">{{ portal_content('home', 'testimonials', 'heading', 'Testimonials', 'شهادات') }}</h2>

        {{-- Featured video story --}}
        <div class="grid md:grid-cols-2 gap-8 lg:gap-16 items-center mb-10">
            <div class="relative rounded-2xl overflow-hidden aspect-video shadow-xl">
                <iframe class="absolute inset-0 w-full h-full" src="https://www.youtube.com/embed/SKrJq4XZYn8"
                        title="{{ portal_content('home', 'testimonials', 'video_title', 'How PAN Emirates scaled their noon store', 'كيف نمت حول الإمارات متجرها على نون') }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
            <div>
                <p class="text-[#feee00] font-black uppercase tracking-wide text-xs mb-2">{{ portal_content('home', 'testimonials', 'eyebrow', 'Seller success story', 'قصة نجاح بائع') }}</p>
                <h3 class="text-white font-black text-lg lg:text-2xl leading-snug mb-4 text-pretty">
                    {{ portal_content('home', 'testimonials', 'featured_title', 'PAN Home chose noon\'s Fulfilled by Partner (FBP) model — and became the #1 furniture seller in the region.', 'اختارت بان هوم نموذج التوصيل عن طريق البائع (FBP) من نون — وأصبحت البائع رقم 1 للأثاث في المنطقة.') }}
                </h3>
                <p class="text-gray-400 text-[15px] mb-4">
                    {{ portal_content('home', 'testimonials', 'featured_subtitle', 'Discover how dedicated support, tailored account management, and powerful tools helped drive their success.', 'اكتشف كيف ساعد الدعم المخصص، والإدارة المصممة خصيصا، والأدوات القوية في تحقيق نجاحهم.') }}
                </p>
                <p class="text-xs font-bold text-[#feee00] tracking-wide">- {{ portal_content('home', 'testimonials', 'featured_name', 'PAN Home', 'بان هوم') }}</p>
            </div>
        </div>

        {{-- Testimonial carousel --}}
        @php
            $testimonials = [
                [
                    'image' => 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-testimonial-05.jpg',
                    'quote_ar' => 'تواصل معنا فريق نون محلي بسرعة وسهّلوا علينا كل الإجراءات. من يوم انضمامنا مع إطلاق البرنامج، والدعم اللي قدموه لنا كان رائع. ساعدونا نفعّل منتجاتنا على المنصة، رفعوا مبيعاتنا، وكان تركيزهم كبير على دعم العلامات المحلية. وبصراحة، لما الناس تفكر تشتري، أول مكان يروحون له هو نون.',
                    'quote_en' => '“noon Mahali reached out to us quickly and made the whole process easy. Since joining at the launch of Mahali, their support has been incredible. They helped us get our products live, boosted our sales, and focused heavily on promoting local brands. And let’s be honest—when people want to shop, they go to noon first.”',
                    'name_ar' => 'حمد البلوشي، المؤسس', 'name_en' => 'Hamad Alblooshi, Founder',
                    'company' => 'H2 Games',
                ],
                [
                    'image' => 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-testimonial-04.jpg',
                    'quote_ar' => 'لطالما كنت شغوفة بالتجارة الإلكترونية، ولهذا قررت البيع على نون والانضمام إلى برنامج مهلي. حصولي على أول دفعة بعد أول عملية بيع كان لحظة مليئة بالتمكين، وجعلتني أشعر بالدعم الحقيقي.',
                    'quote_en' => '“I’ve always been passionate about e-commerce, which is why I chose to sell on noon and join Mahali. Getting paid for my first sale was an empowering moment that made me feel truly supported.”',
                    'name_ar' => 'ليلى السعدي، المؤسسة', 'name_en' => 'Laila Alsaadi, Founder',
                    'company' => 'Heyraat General Trading',
                ],
                [
                    'image' => 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-testimonial-02.jpg',
                    'quote_ar' => 'الشراكة مع نون ساعدت Baybee على النمو بسرعة والوصول إلى عدد أكبر من العملاء في الإمارات والسعودية. دعمهم، وأدواتهم، وانتشارهم الواسع جعلوا الإطلاق سلس والتوسع سهل. نحن متحمسون لمواصلة هذا النجاح معا.',
                    'quote_en' => '“Partnering with noon helped Baybee grow fast and reach more customers across the UAE and KSA. Their support, tools, and reach made launching smooth and scaling easy. We’re excited to keep building on this success together.”',
                    'name_ar' => 'زوبان شالين، مدير العلامة التجارية', 'name_en' => 'Zouban Shalin, Brand Director',
                    'company' => 'Baybee Brand',
                ],
                [
                    'image' => 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-testimonial-01.jpg',
                    'quote_ar' => 'نتوجه بجزيل الشكر لفريق نون على دعمهم الفوري عندما تم حظر حسابنا بشكل مفاجئ. تدخلوا بسرعة، أعادونا للعمل خلال وقت قياسي، وكانوا إلى جانبنا في كل خطوة. خلال ثلاثة أشهر فقط، ارتفعت مبيعاتنا بأكثر من 500٪ — هذا النوع من النمو لا يتحقق إلا بوجود فريق حقيقي يلتزم ويقف مع شركائه بكل احترافية.',
                    'quote_en' => '“Big thanks to the noon team for jumping in when our original account got unexpectedly blocked. They acted fast, got us back online, and had our back throughout. In just three months, our sales shot up by over 500% — that kind of growth only happens when you’ve got a team that truly shows up.”',
                    'name_ar' => 'سريجا في. إس، رئيسة قسم التجارة الإلكترونية', 'name_en' => 'Sreeja V.S, Head of e-Commerce',
                    'company' => 'SAMS Global LLC',
                ],
                [
                    'image' => 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-testimonial-03.jpg',
                    'quote_ar' => 'الشراكة مع نون كانت نقطة تحول، حيث ساهمت في تحقيق نمو مذهل بنسبة 45% مقارنة بالعام الماضي. هذا النجاح لم يكن ليتحقق لولا الدعم المستمر والتفاني من فريق نون، الذين ساندوني في كل خطوة على الطريق.',
                    'quote_en' => '“Partnering with noon has been a game-changer, driving an incredible 45% over-year growth. This wouldn’t have been possible without the unwavering support and dedication of noon team, who stood by me every step of the way.”',
                    'name_ar' => 'حسين الحسن، المؤسس', 'name_en' => 'Hossain Al-Hasan, Founder',
                    'company' => 'Golden Technology Trading',
                ],
            ];
        @endphp

        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
        <div class="flex overflow-x-auto gap-6 lg:gap-7 pb-4 snap-x snap-mandatory no-scrollbar xl:grid xl:grid-cols-5 xl:pb-0 xl:overflow-visible">
            @foreach($testimonials as $i => $t)
                @php
                    $tBlock = 'testimonial_' . ($i + 1);
                    $tImg = portal_image('home', $tBlock, 'photo', $t['image'], $t['name_en'], $t['name_ar']);
                    $tQuote = portal_content('home', $tBlock, 'quote', $t['quote_en'], $t['quote_ar']);
                    $tName = portal_content('home', $tBlock, 'name', $t['name_en'], $t['name_ar']);
                    $tCompany = portal_content('home', $tBlock, 'company', $t['company'], $t['company']);
                @endphp
                <div class="relative rounded-2xl overflow-hidden h-[515px] flex-none snap-start w-[80vw] max-w-[280px] xl:w-full xl:max-w-none">
                    <img src="{{ $tImg['src'] }}" alt="{{ $tImg['alt'] }}"
                         class="absolute inset-0 w-full h-full object-cover object-top">
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/70 to-transparent"></div>
                    <div class="relative h-full flex flex-col justify-end p-4 lg:p-5">
                        <p class="text-white text-[13px] leading-[18px] font-medium line-clamp-6">
                            {{ $tQuote }}
                        </p>
                        <p class="text-white text-xs font-extrabold mt-3">{{ $tName }}</p>
                        <p class="text-[#feee00] text-xs font-extrabold">{{ $tCompany }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
