import React from "react";
import SellerView from "@/src/features/noon/seller";

type Props = {
  params: Promise<{ seller_id: string; locale: string }>;
};

export default async function page({ params }: Props) {
  const { seller_id } = await params;
  return <SellerView sellerId={seller_id} />;
}
