"use server";
import CarouselProducts from "@/src/components/shared/CarouselProducts";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import { Separator } from "@/src/components/ui/separator";
import BaseInfo from "@/src/features/noon/productView/BaseInfo";
import BoughtTogether from "@/src/features/noon/productView/BoughtTogether";
import CouponsSlide from "@/src/features/noon/productView/coupons-slide";
import DeliveryInformation from "@/src/features/noon/productView/delivery-information";
import ExtendedWarranty from "@/src/features/noon/productView/ExtendedWarranty";
import FloatingCartButton from "@/src/features/noon/productView/floating-cart-button";
import PaymentDiscount from "@/src/features/noon/productView/payment-discount";
import ProductImagesPreview from "@/src/features/noon/productView/Product-images-preview";
import ProductOverview from "@/src/features/noon/productView/product-overview";
import { getTranslations } from "next-intl/server";
import Image from "next/image";
import React from "react";
import { getProduct } from "./api/get";
import getLocale from "@/src/helpers/getLocale";
import Variants from "./variants";
import SellerCard from "./seller-card";
import RatingAndReviews from "./rating-and-reviews";

export default async function ProductView({ slug }: { slug: string }) {
  const t = await getTranslations("productView");
  const locale = await getLocale();
  const productData = await getProduct(slug);
  return (
    <div className="container">
      {/* breadcrumb */}
      <Breadcrumb
        list={[
          { label: t("home"), href: "/" },
          ...productData.product.breadcrumbs.map((e) => ({
            label: e.name[locale] as string,
            href: `/${e.slug}`,
          })),
        ]}
      />
      {/* top three cols (images overview, core info & shipping options..., add to cart box) */}
      <div className="grid grid-cols-1 lg:grid-cols-22 md:gap-3 lg:gap-6 items-start">
        {/* product images preview - col one */}
        <div className="lg:col-span-8 lg:sticky top-28">
          <ProductImagesPreview product={productData} />
        </div>
        {/* col two */}
        <div className="lg:col-span-9">
          <BaseInfo product={productData} />
          {!!productData.delivery_options.length && (
            <>
              <Separator className={"my-6"} />
              <DeliveryInformation
                deliveryOptions={productData.delivery_options}
              />
            </>
          )}
          {!!productData.coupons.length && (
            <>
              <Separator className={"my-6"} />
              <CouponsSlide coupons={productData.coupons} />
            </>
          )}
          {!!productData.payment_options.length && (
            <>
              <Separator className={"my-6"} />
              <PaymentDiscount paymentsData={productData.payment_options} />
            </>
          )}
          {!!productData.product_attributes.length && (
            <>
              <Separator className={"my-6"} />
              <Variants variantsData={productData.product_attributes} />
            </>
          )}
          {!!productData.warranty_plans.length && (
            <>
              <Separator className={"my-6"} />
              <ExtendedWarranty
                warrantiesData={productData.warranty_plans}
                listingId={productData.listing.listing_id}
              />
            </>
          )}
          {productData.frequently_bought_together.items.length > 1 && (
            <>
              <Separator className={"my-6"} />
              <BoughtTogether
                boughtTogetherData={productData.frequently_bought_together}
              />
            </>
          )}
        </div>
        {/* col three */}
        <div className="lg:col-span-5">
          {/* seller data */}
          <SellerCard productData={productData} />
          <Image
            src={
              "https://a.nooncdn.com/mpcms/EN0001/assets/d7459e74-052b-4de2-8e11-b87834ad940f.png?width=2400"
            }
            alt="banner"
            width={800}
            height={260}
          />
        </div>
      </div>
      <ProductOverview
        overviewData={{
          highlights: productData.product.highlights,
          overview: productData.product.description,
          specification: productData.product.specifications,
        }}
      />
      <RatingAndReviews reviews={productData.reviews} />
      <CarouselProducts title={t("customersAlsoViewed")} />
      {/* floating add to cart button for small screens */}
      <FloatingCartButton listingId={productData.listing.listing_id} />
    </div>
  );
}
