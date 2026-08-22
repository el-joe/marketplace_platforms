import { products } from "@/public/dummyData";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import { Button } from "@/src/components/ui/button";
import { Separator } from "@/src/components/ui/separator";
import FloatingCartButton from "@/src/features/noon/productView/floating-cart-button";
import {
  ChevronLeft,
  ChevronRight,
  PhoneIcon,
  Redo2Icon,
  ShieldCheckIcon,
  StarIcon,
  StoreIcon,
} from "lucide-react";
import { getLocale, getTranslations } from "next-intl/server";
import Image from "next/image";
import React from "react";
import ProductImagesPreview from "./listing-images-preview";
import ListingDescription from "./description";
import BaseInfo from "./base-info";
import CarouselListings from "@/src/components/shared/carousel-listing";

const breadCrumbList = [
  { label: "Home", href: "/" },
  { label: "lorem1", href: "#" },
  { label: "lorem2", href: "#" },
  { label: "lorem3", href: "#" },
];

export default async function Find() {
  const t = await getTranslations("openSooq.listingView");
  const locale = await getLocale();
  return (
    <div className="container">
      {/* breadcrumb */}
      <Breadcrumb list={breadCrumbList} />
      {/* top three cols (images overview, core info & shipping options..., add to cart box) */}
      <div className="grid grid-cols-1 lg:grid-cols-22 md:gap-3 lg:gap-6 items-start">
        {/* product images preview - col one */}
        <div className="lg:col-span-8 lg:sticky top-22.5">
          <ProductImagesPreview
            images={[
              "https://opensooq-images.os-cdn.com/previews/1024x0/24/ae/24aec2746d189c2beb8d1d58c6bb0ecb8880853050857c9cb245b9a8a8287ae5.jpg.webp",
              "https://opensooq-images.os-cdn.com/previews/400x0/9b/fa/9bfa55df81d0cf939cbea74a7ebfa3e4108d663408ddfa1f89215c6561126b04.jpg.webp",
              "https://opensooq-images.os-cdn.com/previews/0x240/65/76/657616d448dc42df4a7e5a2f2137294e8dca18358ef3ab1bbc1bdb489c45550c.jpg.webp",
              "https://opensooq-images.os-cdn.com/previews/1024x0/36/fe/36fec9684f790f413c11cd9bceb745beb960c0dfa36597d725cc0d9f014c8797.jpg.webp",
              "https://opensooq-images.os-cdn.com/previews/1024x0/bb/eb/bbeb9ae7904ea02e9ef7ff8e427fa009680024b5f7da207f1d9b85480dbf0457.jpg.webp",
              "https://opensooq-images.os-cdn.com/previews/1024x0/2e/e1/2ee1041a57aa7e0807a7f9525157b18cd0eeb8348019b47aea07ace0955becd1.jpg.webp",
            ]}
          />
        </div>
        {/* col two */}
        <div className="lg:col-span-9">
          <BaseInfo product={products[0]} />
          <Separator className={"my-6"} />
          <ListingDescription />
        </div>
        {/* col three */}
        <div className="lg:col-span-5">
          <div className="border border-border rounded-md mb-6">
            {/* "sold by" info */}
            <div className="p-4">
              <div className="flex items-center gap-2">
                <div className="flex pace-items-center bg-gray-2 rounded-lg p-2">
                  <StoreIcon />
                </div>
                <div className="text-sm">
                  <p className="flex">
                    {t("soldBy")}
                    <span className="font-bold ps-1">Seller</span>
                    {locale === "ar" ? (
                      <ChevronLeft className="w-5 h-5" />
                    ) : (
                      <ChevronRight className="w-5 h-5" />
                    )}
                  </p>
                  <div className="flex gap-1 items-center text-green">
                    <StarIcon className="fill-green w-4" />
                    <p>4.2</p>
                    <Separator orientation="vertical" className={"mx-1"} />
                    <p className="text-gray">86% {t("positive")}</p>
                  </div>
                </div>
              </div>
              <div className="flex flex-col gap-2 items-stretch mt-4">
                <div className="flex items-center justify-between bg-gray-2 py-1 px-2 text-gray text-sm rounded-md">
                  <p>{t("memberSince")}</p>
                  <p className="text-green font-bold">5+ Y</p>
                </div>
                <div className="flex items-center justify-between bg-gray-2 py-1 px-2 text-gray text-sm rounded-md">
                  <p>{t("greatRecentRating")}</p>
                </div>
              </div>
            </div>
            <Separator />
            <div className="p-4 text-sm hidden lg:block">
              <Button
                size={"lg"}
                className={
                  "mx-auto bg-blue text-white min-h-9! flex text-lg w-full py-2 rounded-xl uppercase"
                }
              >
                <PhoneIcon />
                <span>01111111111</span>
              </Button>
            </div>
          </div>
          <Image
            src={
              "https://eg.opensooq.com/_next/image?url=https%3A%2F%2Fopensooq-images.os-cdn.com%2Foriginals%2Fmedia%2F40%2F35%2F40352566628d710e079bc8de7d4c29b55a395311b51b2921050a16f408b95022.png&w=1080&q=65"
            }
            alt="banner"
            width={800}
            height={260}
          />
        </div>
      </div>

      <CarouselListings title={t("customersAlsoViewed")} />
      {/* floating add to cart button */}
      <FloatingCartButton />
    </div>
  );
}
