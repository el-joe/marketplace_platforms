import { getHomeService } from "./api/get";
import { DynamicLayout } from "./dynamic-layout";

export default async function Home() {
  const homeData = await getHomeService();
  return (
    <div className="bg-white">
      {homeData.data.page_builder.sections.map((e) => (
        <DynamicLayout key={e.id} section={e} />
      ))}

      {/* <HeroBanner /> */}
      {/* categories slide */}
      {/* <CategoriesSlide /> */}
      {/* the next section only showing for large screens >1024 */}
      {/* <ThreeColDealsSection /> */}
      {/* the next section only showing for small screens <1024 */}
      {/* <OffersForYouSlide /> */}
      {/* ad banner */}
      {/* <div className="container">
        <AdBanner
          imageSrc="https://a.nooncdn.com/assets/img-1440x1440/en_dk_uae-sfu-01_-_2026-05-25T132401.022.1779695674.3316123.png?width=2400"
          href="/"
        />
      </div> */}
      {/* recommended for you section */}
      {/* <CarouselProducts title={t("recommendedForYou")} /> */}
      {/* maximize your savings section */}
      {/* <MaximizeYourSavingsSection /> */}
      {/* bestsellers for you section */}
      {/* <CarouselProducts title={t("bestsellersForYou")} /> */}
      {/* spotlight deals section */}
      {/* <SpotlightDeals /> */}
      {/* mega deals section   (for small screens) */}
      {/* <div className="lg:hidden">
        <MegaDeals />
      </div> */}
      {/* travel store section */}
      {/* <TravelStoreSection /> */}
      {/* top deals section */}
      {/* <CarouselProducts title={"Top deals in TVs & home"} /> */}
      {/* medial banners slide */}
      {/* <BannersSlides /> */}
      {/* top deals section */}
      {/* <CarouselProducts title={"Top bulk deals in grocery"} /> */}
      {/* up to (offers) section */}
      {/* <UptoSection /> */}
    </div>
  );
}
