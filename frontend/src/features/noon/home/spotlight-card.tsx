import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import { PlusIcon } from "lucide-react";
import Image from "next/image";
import React from "react";
import { Product } from "./types";
import useLocale from "@/src/hooks/use-locale";
import { Link } from "@/i18n/navigation";
import { useCartContext } from "@/src/providers/cart-provider";
import { Spinner } from "@/src/components/ui/spinner";

const SpotlightCard = ({ data }: { data: Product }) => {
  const locale = useLocale();
  const { addItem, isMutating, targetItemMutating } = useCartContext();
  return (
    <Link
      href={`/products/${data?.url_param}`}
      className="w-38 lg:w-48 xl:w-96 rounded-md overflow-hidden flex flex-col gap-1 lg:gap-2 bg-gray-2 h-full"
    >
      {/* card top (image, label, cart button ) */}
      <div className="relative bg-white">
        {/* top right label */}
        {data?.category_name?.[locale] && (
          <div className="absolute top-0 right-0 bg-green-2 text-white px-2 py-0.5 text-[9px] lg:text-sm rounded-bl-md">
            {data.category_name?.[locale]}
          </div>
        )}
        {/* cart button */}
        <Button
          variant={"outline"}
          className={
            "absolute bottom-1 lg:bottom-2 right-2 z-10 rounded-md! p-1! xl:p-3! min-w-0! min-h-0! aspect-square"
          }
          disabled={isMutating && targetItemMutating === data.listing_id}
          onClick={(e) => {
            e.preventDefault();
            addItem({ quantity: 1, vendorListingId: data.listing_id });
          }}
        >
          {isMutating && targetItemMutating === data.listing_id ? (
            <Spinner />
          ) : (
            <PlusIcon className={`size-4`} />
          )}
        </Button>
        {/* image */}
        <Image
          src={data?.primary_image}
          alt={locale === "ar" ? data.name_ar : data?.name_en}
          width={400}
          height={700}
          className="h-36 lg:h-44 object-contain"
        />
      </div>
      {/* card body (title, price) */}
      <div className="p-2 flex flex-col gap-0.5 lg:gap-2">
        <h3 className="text-sm lg:text-base line-clamp-2">
          {locale === "ar" ? data.name_ar : data?.name_en}
        </h3>
        <Price
          currentPrice={data.price_formatted}
          currency={data?.currency}
          size="lg"
        />
      </div>
    </Link>
  );
};

export default SpotlightCard;
