"use client";
import Image from "next/image";
import React from "react";
import { IPrepareCheckout } from "./types/checkout.type";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";

type Props = {
  selectedPaymentMethod: string;
  methods: IPrepareCheckout["available_payment_gateways"];
  setPaymentMethod: (method: string) => void;
};

export default function PaymentMethodsCard({
  methods,
  selectedPaymentMethod,
  setPaymentMethod,
}: Props) {
  const t = useTranslations("checkout");
  const locale = useLocale();
  return (
    <div className="p-3 bg-white rounded-2xl">
      <h3 className="text-base font-semibold mb-3">{t("payWith")}</h3>
      <div className="flex flex-col gap-2">
        {methods.map((m) => (
          <div
            key={m.id}
            className={`${m.id === selectedPaymentMethod ? "bg-light-green border border-blue-2" : "bg-gray-2"} p-3 flex items-center gap-3 rounded-xl cursor-pointer`}
            onClick={() => setPaymentMethod(m.id)}
          >
            <Image
              src={m?.image_url || ""}
              alt={m?.display_name[locale] || ""}
              width={210}
              height={120}
              className="w-16 aspect-video object-contain"
            />
            <div>
              <p className="text-base font-semibold mb-1">
                {m.display_name[locale]}
              </p>
              <p className="text-gray text-sm">{m.display_name[locale]}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
