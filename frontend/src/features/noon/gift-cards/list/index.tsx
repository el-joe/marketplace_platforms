import Image from "next/image";
import BrandsStrip from "./components/brands-strip";
import FaqSection from "./components/faq-section";
import GiftCardsList from "./components/gift-cards-list";

export default function GiftCards() {
  return (
    <div className=" py-6">
      <Image
        src="/images/banner_image_web_url_ae-en_v2.avif"
        alt=""
        width={1400}
        height={298}
        className="h-auto w-full"
        priority
      />
      <GiftCardsList />
      <BrandsStrip />
      <Image
        src="/images/desktop_redeem_banner_en.avif"
        alt=""
        width={1400}
        height={253}
        className="h-auto w-full"
      />{" "}
      <FaqSection />
    </div>
  );
}
