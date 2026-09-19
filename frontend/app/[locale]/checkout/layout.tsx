import CheckoutFooter from "@/src/layout/checkout/footer";
import { CheckoutHeader } from "@/src/layout/checkout/header";
import React from "react";

type Props = { children: React.ReactNode };

export default function layout({ children }: Props) {
  return (
    <div>
      <CheckoutHeader />
      {children}
      <CheckoutFooter />
    </div>
  );
}
