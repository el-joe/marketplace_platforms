"use client";
import ReceiverSelectDialog from "@/src/components/shared/dialogs/receiver-select-dialog/receiver-select-dialog";
import { Button } from "@/src/components/ui/button";
import { getReceivers, Receiver } from "@/src/services/receiver";
import { useQuery } from "@tanstack/react-query";
import { PhoneIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import React from "react";

export default function OrderReceiverCard({
  selectedReceiverId,
  onSelectReceiver,
}: {
  selectedReceiverId?: string | null;
  onSelectReceiver: (receiver: Receiver) => void;
}) {
  const t = useTranslations("checkout");
  const receivers = useQuery({
    queryKey: ["receivers"],
    queryFn: getReceivers,
  });
  const selectedReceiver =
    receivers.data?.find((r) => r.id === selectedReceiverId) ??
    receivers.data?.find((r) => r.is_default) ??
    receivers.data?.[0];

  return (
    <div className="p-3 rounded-2xl bg-white flex-1">
      <h4 className="text-base font-semibold mb-2">
        {t("whoWillReceiveThisOrder")}?
      </h4>
      <div className="flex items-center p-3 bg-gray-2 rounded-lg gap-3">
        <PhoneIcon />
        <div>
          <p className="font-semibold">{selectedReceiver?.name}</p>
          <p className="text-gray text-sm">{selectedReceiver?.phone}</p>
        </div>
        <ReceiverSelectDialog
          selectedReceiverId={selectedReceiverId}
          onSelect={onSelectReceiver}
          triggerButton={
            <Button
              variant={"ghost"}
              className={"bg-transparent text-blue-2 text-sm ms-auto"}
            >
              {t("changeReceiver")}
            </Button>
          }
        />
      </div>
    </div>
  );
}
