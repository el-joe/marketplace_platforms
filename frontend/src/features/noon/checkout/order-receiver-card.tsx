import { Button } from "@/src/components/ui/button";
import { getAddresses } from "@/src/services/address";
import { useQuery } from "@tanstack/react-query";
import { PhoneIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import React from "react";

export default function OrderReceiverCard() {
  const t = useTranslations("checkout");
  const addresses = useQuery({
    queryKey: ["addresses"],
    queryFn: getAddresses,
  });
  const defaultAddress = addresses.data?.find((a) => a.is_default);
  return (
    <div className="p-3 rounded-2xl bg-white flex-1">
      <h4 className="text-base font-semibold mb-2">
        {t("whoWillReceiveThisOrder")}?
      </h4>
      <div className="flex items-center p-3 bg-gray-2 rounded-lg gap-3">
        <PhoneIcon />
        <div>
          <p className="font-semibold">{defaultAddress?.recipient_name}</p>
          <p className="text-gray text-sm">{defaultAddress?.recipient_phone}</p>
        </div>
        <Button
          variant={"ghost"}
          className={"bg-transparent text-blue-2 text-sm ms-auto"}
        >
          {t("changeReceiver")}
        </Button>
      </div>
    </div>
  );
}
