import React from "react";
import { notFound } from "next/navigation";
import SellerView from "@/src/features/noon/seller";
import { getSellerProfile, SellerNotFoundError } from "@/src/features/noon/seller/api";

type Props = {
  params: Promise<{ seller_id: string; locale: string }>;
};

export default async function page({ params }: Props) {
  const { seller_id } = await params;

  let seller;
  try {
    seller = await getSellerProfile(seller_id);
  } catch (error) {
    if (error instanceof SellerNotFoundError) {
      notFound();
    }
    throw error;
  }

  return <SellerView sellerId={seller_id} initialData={seller} />;
}
