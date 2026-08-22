import React from "react";
import CheckoutSuccess from "@/src/features/noon/checkout/success";

type Props = {
  searchParams: Promise<{ [key: string]: string | string[] | undefined }>;
};

export default async function CheckoutSuccessPage({ searchParams }: Props) {
  const resolvedSearchParams = await searchParams;
  const rawOrderNumber = resolvedSearchParams?.order_number;
  const orderNumber = Array.isArray(rawOrderNumber)
    ? rawOrderNumber[0]
    : rawOrderNumber;

  return <CheckoutSuccess orderNumber={orderNumber} />;
}
