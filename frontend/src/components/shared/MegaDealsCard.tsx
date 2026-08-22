"use client";
import Image from "next/image";
import React from "react";
import Price from "./Price";
import { Button } from "../ui/button";
import { PlusIcon } from "lucide-react";
import { Product } from "@/src/features/noon/home/types";
import useLocale from "@/src/hooks/use-locale";
import { useCartContext } from "@/src/providers/cart-provider";
import { Link } from "@/i18n/navigation";

const MegaDealsCard = ({ data }: { data: Product }) => {
  const locale = useLocale();
  const { addItem } = useCartContext();
  return (
    <div className="rounded-lg overflow-hidden w-[calc(100%/2-1rem)]">
      <div className="bg-background relative">
        {/* badge */}
        {/* <div className="absolute top-0 right-0 md:relative md:ms-auto md:mb-1 bg-green-2 px-2 xl:px-3.5 xl:py-0.5 rounded-bl-lg w-fit text-white text-xs xl:text-sm line-clamp-1">
     
        </div> */}
        {/* image */}
        <Image
          src={data?.thumbnail}
          alt={data?.name_en}
          width={600}
          height={400}
          className="h-56 lg:h-16 xl:h-23 2xl:h-35 object-contain"
        />
        {/* add to cat button */}
        <Button
          className={
            "absolute bottom-3 right-3 border border-gray-2 p-1! xl:p-2! min-h-0!"
          }
          onClick={() =>
            addItem({ quantity: 1, vendorListingId: data?.listing_id })
          }
        >
          <PlusIcon />
        </Button>
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
