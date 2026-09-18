import Image from "next/image";
import { getGiftCardsPageContent } from "../api/gift-cards.actions";
import BrandsStrip from "./components/brands-strip";
import FaqSection from "./components/faq-section";
import GiftCardsList from "./components/gift-cards-list";

export default async function GiftCards() {
  const { banners, faqs } = await getGiftCardsPageContent();

  const heroBanner = banners.gift_cards_hero;
  const redeemBanner = banners.gift_cards_redeem;

  return (
    <div className=" py-6">
      <Image
        src={heroBanner?.desktop_image_url || "/images/banner_image_web_url_ae-en_v2.avif"}
        alt={heroBanner?.title_en || ""}
        width={1400}
        height={298}
        className="h-auto w-full"
        priority
      />
      <GiftCardsList />
      <BrandsStrip />
      <Image
        src={redeemBanner?.desktop_image_url || "/images/desktop_redeem_banner_en.avif"}
        alt={redeemBanner?.title_en || ""}
        width={1400}
        height={253}
        className="h-auto w-full"
      />{" "}
      <FaqSection faqs={faqs} />
    </div>
  );
}
