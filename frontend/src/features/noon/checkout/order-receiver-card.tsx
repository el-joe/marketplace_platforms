"use client";

import { Button } from "@/src/components/ui/button";
import { getAddresses } from "@/src/services/address";
import { getReceivers, Receiver } from "@/src/services/receiver";
import { useQuery } from "@tanstack/react-query";
import { PhoneIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import React, { useMemo } from "react";
import ReceiversDialog from "./dialogs/receivers-dialog";

interface OrderReceiverCardProps {
  selectedReceiverId?: string | null;
  onSelectReceiver?: (receiver: Receiver) => void;
}

export default function OrderReceiverCard({
  selectedReceiverId,
  onSelectReceiver,
}: OrderReceiverCardProps = {}) {
  const t = useTranslations("checkout");

  const addresses = useQuery({
    queryKey: ["addresses"],
    queryFn: getAddresses,
  });

  const receivers = useQuery({
    queryKey: ["receivers"],
    queryFn: getReceivers,
  });

  const defaultAddress = addresses.data?.find((a) => a.is_default);

  const activeReceiver = useMemo(() => {
    if (!receivers.data?.length) return null;
    if (selectedReceiverId) {
      const found = receivers.data.find((r) => r.id === selectedReceiverId);
      if (found) return found;
    }
    return receivers.data.find((r) => r.is_default) || receivers.data[0];
  }, [receivers.data, selectedReceiverId]);

  const receiverName =
    activeReceiver?.name || defaultAddress?.recipient_name || "";
  const receiverPhone =
    activeReceiver?.phone || defaultAddress?.recipient_phone || "";

  return (
    <div className="p-3 rounded-2xl bg-white flex-1">
      <h4 className="text-base font-semibold mb-2">
        {t("whoWillReceiveThisOrder")}?
      </h4>
      <div className="flex items-center p-3 bg-gray-2 rounded-lg gap-3">
        <PhoneIcon className="size-5 shrink-0 text-gray-700" />
        <div className="min-w-0 flex-1">
          <p className="font-semibold truncate">{receiverName}</p>
          <p className="text-gray text-sm">{receiverPhone}</p>
        </div>
        <ReceiversDialog
          selectedReceiverId={activeReceiver?.id ?? selectedReceiverId}
          onReceiverSelected={onSelectReceiver}
          triggerButton={
            <Button
              variant={"ghost"}
              className={
                "bg-transparent text-blue-2 text-base font-semibold ms-auto"
              }
            >
              {t("changeReceiver")}
            </Button>
          }
        />
      </div>
    </div>
  );
}
