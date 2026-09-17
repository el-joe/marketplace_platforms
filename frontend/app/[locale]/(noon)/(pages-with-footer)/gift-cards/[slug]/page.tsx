import { notFound } from "next/navigation";
import { getGiftCardBatch } from "@/src/features/noon/gift-cards/api/gift-cards.actions";
import GiftCardView from "@/src/features/noon/gift-cards/view";

type Props = {
  params: Promise<{ slug: string }>;
};

export default async function GiftCardViewPage({ params }: Props) {
  const { slug } = await params;
  // `slug` is the gift-card batch id (see gift-card-offer-card.tsx's
  // `/gift-cards/${giftCard.id}` link).
  const batch = await getGiftCardBatch(slug, "AED");

  if (!batch) {
    notFound();
  }

  return <GiftCardView batch={batch} />;
}
