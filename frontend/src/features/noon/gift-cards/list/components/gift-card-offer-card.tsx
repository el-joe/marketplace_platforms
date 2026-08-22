import Image from "next/image";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import type { GiftCardBatch } from "../../helpers/types";

type Props = {
  giftCard: GiftCardBatch;
  title: string;
};

export default function GiftCardOfferCard({ giftCard, title }: Props) {
  return (
    <Card className="flex h-[220px] flex-col gap-3 p-3 transition-shadow hover:shadow-md">
      <div className="relative w-full flex-1 overflow-hidden rounded-lg bg-gray-2">
        <Image
          src={giftCard.image_url}
          alt={title}
          fill
          sizes="240px"
          className="object-cover"
        />
      </div>

      <div>
        <p className="truncate text-sm font-bold">{title}</p>
        <Price
          currentPrice={Number(giftCard.amount)}
          currency={giftCard.currency_code}
          size="sm"
          className="mt-1"
        />
      </div>
    </Card>
  );
}
