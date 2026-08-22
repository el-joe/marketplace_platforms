import { notFound } from "next/navigation";
import { getGiftCardCategory } from "@/src/features/noon/gift-cards/data";
import GiftCardView from "@/src/features/noon/gift-cards/view";

type Props = {
  params: Promise<{ slug: string }>;
};

export default async function GiftCardViewPage({ params }: Props) {
  const { slug } = await params;
  const category = getGiftCardCategory(slug);

  if (!category) {
    notFound();
  }

  return <GiftCardView category={category} />;
}
