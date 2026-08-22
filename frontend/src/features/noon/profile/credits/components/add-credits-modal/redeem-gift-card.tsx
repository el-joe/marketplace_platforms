"use client";

import { useState } from "react";
import { Info } from "lucide-react";
import { useTranslations } from "next-intl";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/src/components/ui/tabs";
import { Input } from "@/src/components/ui/base-inputs/input";
import { Button } from "@/src/components/ui/button";

export default function RedeemGiftCard() {
  const t = useTranslations("profile");

  const [giftCardNumber, setGiftCardNumber] = useState("");
  const [pin, setPin] = useState("");
  const [voucherCode, setVoucherCode] = useState("");

  return (
    <Tabs defaultValue="gift-card">
      <TabsList variant="line" className="w-full">
        <TabsTrigger value="gift-card" className="flex-1">
          {t("giftCardTab")}
        </TabsTrigger>
        <TabsTrigger value="voucher" className="flex-1">
          {t("voucherTab")}
        </TabsTrigger>
      </TabsList>

      <TabsContent value="gift-card" className="flex flex-col gap-4 pt-4">
        <div className="flex items-start gap-2 bg-yellow/10 border border-yellow/30 rounded-lg p-3 text-sm text-light">
          <Info className="size-4 shrink-0 mt-0.5" />
          <p>{t("giftCardPinNotice")}</p>
        </div>

        <div>
          <label className="text-sm font-medium mb-1.5 block">
            {t("giftCardNumberLabel")}
          </label>
          <Input
            className="h-12"
            placeholder={t("giftCardNumberPlaceholder")}
            value={giftCardNumber}
            onChange={(e) => setGiftCardNumber(e.target.value)}
          />
        </div>

        <div>
          <label className="text-sm font-medium mb-1.5 block">
            {t("pinLabel")}
          </label>
          <Input
            className="h-12"
            placeholder={t("pinPlaceholder")}
            value={pin}
            onChange={(e) => setPin(e.target.value)}
          />
        </div>

        <Button
          disabled={!giftCardNumber || !pin}
          className="bg-black text-white uppercase font-semibold h-12 rounded-md w-full disabled:bg-gray-2 disabled:text-gray"
        >
          {t("redeem")}
        </Button>
      </TabsContent>

      <TabsContent value="voucher" className="flex flex-col gap-4 pt-4">
        <div>
          <label className="text-sm font-medium mb-1.5 block">
            {t("voucherCodeLabel")}
          </label>
          <Input
            className="h-12"
            placeholder={t("voucherCodePlaceholder")}
            value={voucherCode}
            onChange={(e) => setVoucherCode(e.target.value)}
          />
        </div>

        <Button
          disabled={!voucherCode}
          className="bg-black text-white uppercase font-semibold h-12 rounded-md w-full disabled:bg-gray-2 disabled:text-gray"
        >
          {t("redeem")}
        </Button>
      </TabsContent>
    </Tabs>
  );
}
