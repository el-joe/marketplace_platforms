import { Banner } from "../banner";
import { FlashSale } from "../flash-sale";
import HeroSlider from "../hero-slider";
import ImagesGrid from "../images-grid";
import { ImagesSlider } from "../images-slider";
import MegaDeals from "../mega-deals";
import { ProductsSlider } from "../products-slied";

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
