"use client";
import Image from "next/image";
import Price from "../../Price";
import { Product } from "@/types/globals";
import useLocale from "@/src/hooks/use-locale";
import { Link } from "@/i18n/navigation";
import AddToCartButton from "@/src/components/shared/add-to-cart-button";
import { getImageURL } from "@/src/helpers/get-image-url";
import { getListingImage } from "@/src/types/media";

const MegaDealsCard = ({ data }: { data: Product }) => {
  const locale = useLocale();
  return (
    <div className="rounded-lg overflow-hidden w-[calc(100%/2-0.5rem)] bg-gray-2">
      <div className="bg-background relative">
        {/* badge */}
        <div className="absolute top-0 right-0 md:relative md:ms-auto md:mb-1 bg-green-2 px-2 xl:px-3.5 xl:py-0.5 rounded-es-lg w-fit text-white text-xs xl:text-sm line-clamp-1">
          {data?.category_name?.[locale]}
        </div>
        {/* image */}
        <Image
          src={getImageURL(getListingImage(data))}
          alt={(locale === "ar" ? data?.name_ar : data?.name_en) ?? ""}
          width={900}
          height={600}
          className="h-46 lg:h-18 xl:h-22 2xl:h-40 object-contain"
        />
        {/* add to cat button */}
        <AddToCartButton
          listingId={data?.listing_id}
          size="sm"
          hasCustomAttributes={!!data?.has_custom_attributes}
          customAttributes={data?.custom_attributes ?? []}
        />
      </div>
      {/* body */}
      <Link
        href={`/products/${data?.url_param}`}
        className="px-3 py-1 bg-gray-2 block"
      >
        <h4 className="text-xs xl:text-sm line-clamp-2 mb-1">
          {locale === "ar" ? data?.name_ar : data?.name_en}
        </h4>
        <Price currentPrice={data?.price} currency={data?.currency} />
      </Link>
    </div>
  );
};

export default MegaDealsCard;
