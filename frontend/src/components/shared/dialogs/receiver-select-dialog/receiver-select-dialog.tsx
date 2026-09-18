"use client";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Button } from "@/src/components/ui/button";
import { getReceivers, Receiver } from "@/src/services/receiver";
import { useQuery } from "@tanstack/react-query";
import { CheckIcon, PhoneIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { JSXElementConstructor, useState } from "react";

type Props = {
  triggerButton: React.ReactElement<
    unknown,
    string | JSXElementConstructor<unknown>
  >;
  selectedReceiverId?: string | null;
  onSelect: (receiver: Receiver) => void;
};

export default function ReceiverSelectDialog({
  triggerButton,
  selectedReceiverId,
  onSelect,
}: Props) {
  const t = useTranslations("checkout");
  const [open, setOpen] = useState(false);

  const receivers = useQuery({
    queryKey: ["receivers"],
    queryFn: getReceivers,
    enabled: open,
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={triggerButton} />
      <DialogContent className="lg:min-w-md! max-w-lg!">
        <DialogHeader>
          <DialogTitle className="text-xl font-bold">
            {t("changeReceiver")}
          </DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-2 max-h-96 overflow-auto">
          {receivers.data?.map((receiver) => {
            const isSelected =
              receiver.id === (selectedReceiverId ?? undefined) ||
              (!selectedReceiverId && receiver.is_default);
            return (
              <button
                key={receiver.id}
                type="button"
                onClick={() => {
                  onSelect(receiver);
                  setOpen(false);
                }}
                className={
                  "flex items-center gap-3 p-3 rounded-lg border text-start w-full " +
                  (isSelected
                    ? "border-blue-2 bg-blue-2/5"
                    : "border-gray-2 bg-white")
                }
              >
                <PhoneIcon className="shrink-0" />
                <div className="flex-1">
                  <p className="font-semibold">{receiver.name}</p>
                  <p className="text-gray text-sm">{receiver.phone}</p>
                </div>
                {isSelected && (
                  <CheckIcon className="text-blue-2 shrink-0" />
                )}
              </button>
            );
          })}
          {!receivers.isPending && receivers.data?.length === 0 && (
            <p className="text-gray text-sm text-center py-4">
              {t("noReceivers")}
            </p>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
