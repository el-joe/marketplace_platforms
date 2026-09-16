"use client";
import Image from "next/image";
import { IPrepareCheckout } from "./types/checkout.type";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";

type Props = {
  selectedPaymentMethod: string;
  methods: IPrepareCheckout["available_payment_gateways"];
  setPaymentMethod: (method: string) => void;
  total: number;
  walletBalance: number;
};

export default function PaymentMethodsCard({
  methods,
  selectedPaymentMethod,
  setPaymentMethod,
  total,
  walletBalance,
}: Props) {
  const t = useTranslations("checkout");
  const locale = useLocale();

  return (
    <div className="p-3 bg-white rounded-2xl">
      <h3 className="text-base font-semibold mb-3">{t("payWith")}</h3>
      <div className="flex flex-col gap-2">
        {methods.map((m) => {
          const isWallet = m.gateway_code === "wallet";
          const isDisabled = isWallet && total > walletBalance;

          return (
            <div
              key={m.id}
              className={`${m.id === selectedPaymentMethod && !isDisabled ? "bg-light-green border border-blue-2" : "bg-gray-2"} ${isDisabled ? "opacity-50 cursor-not-allowed" : "cursor-pointer"} p-3 flex items-center gap-3 rounded-xl`}
              onClick={() => !isDisabled && setPaymentMethod(m.id)}
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
                <p
                  className={
                    isDisabled ? "text-red text-sm" : "text-gray text-sm"
                  }
                >
                  {isDisabled
                    ? t("insufficientWalletBalance")
                    : m.display_name[locale]}
                </p>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
