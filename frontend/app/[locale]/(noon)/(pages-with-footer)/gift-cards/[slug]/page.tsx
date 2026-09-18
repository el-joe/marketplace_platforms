import { notFound } from "next/navigation";
import { getGiftCardBatch } from "@/src/features/noon/gift-cards/api/gift-cards.actions";
import GiftCardView from "@/src/features/noon/gift-cards/view";

type Props = {
  params: Promise<{ slug: string }>;
};

export default async function GiftCardViewPage({ params }: Props) {
  const { slug } = await params;
  const batch = await getGiftCardBatch(slug, "AED");

  if (!batch) {
    notFound();
  }

  return <GiftCardView batch={batch} />;
}
