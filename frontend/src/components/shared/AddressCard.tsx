import { Building2Icon } from "lucide-react";
import { cn } from "@/src/lib/utils";

type Props = {
  label: React.ReactNode;
  icon?: React.ReactNode;
  addressLine: string;
  receiverName: string;
  receiverPhone: string;
  verified?: boolean;
  verifyTrigger?: React.ReactNode;
  editTrigger?: React.ReactNode;
  deleteTrigger?: React.ReactNode;
  /** Either a "default" badge or a "set as default" button — caller decides which. */
  defaultSlot?: React.ReactNode;
  className?: string;
};

export default function AddressCard({
  label,
  icon,
  addressLine,
  receiverName,
  receiverPhone,
  verified,
  verifyTrigger,
  editTrigger,
  deleteTrigger,
  defaultSlot,
  className,
}: Props) {
  return (
    <div
      className={cn(
        "overflow-hidden rounded-xl border border-blue-3 bg-white",
        className,
      )}
    >
      <div className="p-4">
        <div className="mb-2 flex items-center gap-3">
          <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gray-2">
            {icon ?? <Building2Icon className="size-5" />}
          </div>
          <span className="text-lg font-bold">{label}</span>
        </div>

        <p>{addressLine}</p>

        <div className="mt-4 flex items-center justify-between gap-2">
          <p className="text-sm text-gray">
            {receiverName} , {receiverPhone}
          </p>
          {!verified && verifyTrigger}
        </div>
      </div>

      {(editTrigger || deleteTrigger || defaultSlot) && (
        <div className="flex items-center justify-between gap-3 border-t border-border bg-gray-3 px-4 py-3">
          <div>{defaultSlot}</div>
          <div className="flex items-center gap-4">
            {editTrigger}
            {deleteTrigger}
          </div>
        </div>
      )}
    </div>
  );
}
