import { Banner } from "../sections/banner";
import { FlashSale } from "../sections/flash-sale";
import HeroSlider from "../sections/hero-slider";
import ImagesGrid from "../sections/images-grid";
import { ImagesSlider } from "../sections/images-slider";
import MegaDeals from "../sections/mega-deals";
import { ProductsSlider } from "../sections/products-slied";

export const blocks = {
  hero_slider: HeroSlider,
  full_banner: Banner,
  image_slider: ImagesSlider,
  mega_deals: MegaDeals,
  product_row: ProductsSlider,
  ad_images_2col: ImagesGrid,
  promo_tiles: ImagesGrid,
  flash_sale: FlashSale,
};
