import ProductView from "@/src/features/noon/productView";
import { getProduct } from "@/src/features/noon/productView/api/get";
import getLocale from "@/src/helpers/getLocale";
import type { Metadata } from "next";
import React from "react";

type Props = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const locale = await getLocale();
  const product = await getProduct(slug);
  const title =
    product.variant?.variant_name || product.product.name[locale];

  return { title };
}

export default async function page({ params }: Props) {
  const { slug } = await params;
  return <ProductView slug={slug} />;
}
