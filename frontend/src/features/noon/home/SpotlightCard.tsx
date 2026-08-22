import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import { PlusIcon } from "lucide-react";
import Image from "next/image";
import React from "react";

type Props = {
  data: {
    image: string;
    title: string;
    oldPrice: number;
    price: number;
    label: string;
  };
};

const SpotlightCard = ({ data }: Props) => {
  return (
    <div className="w-38 lg:w-48 xl:w-96 rounded-md overflow-hidden flex flex-col gap-1 lg:gap-2 bg-gray-2">
      {/* card top (image, label, cart button ) */}
      <div className="relative bg-white">
        {/* top right label */}
        {data.label && (
          <div className="absolute top-0 right-0 bg-green-2 text-white px-2 py-0.5 text-[9px] lg:text-sm rounded-bl-md">
            {data.label}
          </div>
        )}
        {/* cart button */}
        <Button
          variant={"outline"}
          className={
            "absolute bottom-1 lg:bottom-2 right-2 z-10 rounded-md! p-1! xl:p-3! min-w-0! min-h-0! aspect-square"
          }
        >
          <PlusIcon className={`size-3 lg:size-4 `} />
        </Button>
        {/* image */}
        <Image
          src={data.image}
          alt={data.title}
          width={400}
          height={700}
          className="h-36 lg:h-44 object-contain"
        />
      </div>
      {/* card body (title, price) */}
      <div className="p-2 flex flex-col gap-0.5 lg:gap-2">
        <h3 className="text-sm lg:text-base line-clamp-2">{data.title}</h3>
        <Price currentPrice={data.price} oldPrice={data.oldPrice} size="lg" />
      </div>
    </div>
  );
};

export default SpotlightCard;
