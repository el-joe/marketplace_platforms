"use client";
import Image from "next/image";
import Price from "../../../components/shared/Price";
import { Product } from "@/src/features/noon/home/types";
import useLocale from "@/src/hooks/use-locale";
import { Link } from "@/i18n/navigation";
import AddToCartButton from "@/src/components/shared/add-to-cart-button";

const MegaDealsCard = ({ data }: { data: Product }) => {
  const locale = useLocale();
  return (
    <div className="rounded-lg overflow-hidden w-[calc(100%/2-0.5rem)] bg-gray-2">
      <div className="bg-background relative">
        {/* badge */}
        <div className="absolute top-0 right-0 md:relative md:ms-auto md:mb-1 bg-green-2 px-2 xl:px-3.5 xl:py-0.5 rounded-bl-lg w-fit text-white text-xs xl:text-sm line-clamp-1">
          {data?.category_name?.[locale]}
        </div>
        {/* image */}
        <Image
          src={data?.thumbnail}
          alt={data?.name_en}
          width={900}
          height={600}
          className="h-46 lg:h-18 xl:h-22 2xl:h-40 object-contain"
        />
        {/* add to cat button */}
        <AddToCartButton listingId={data?.listing_id} size="sm" />
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
