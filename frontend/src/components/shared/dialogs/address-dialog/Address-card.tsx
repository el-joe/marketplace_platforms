import {
  BadgeCheckIcon,
  BriefcaseBusinessIcon,
  HomeIcon,
  MapPinIcon,
} from "lucide-react";
import { cn } from "@/src/lib/utils";
import { Address } from "@/src/services/address";
import { Separator } from "@/src/components/ui/separator";
import { useAddressesContext } from "@/src/providers/addresses-provider";

type Props = {
  address: Address;
  className?: string;
  handleCloseDialog?: () => void;
};

export default function AddressCard({
  address,
  className,
  handleCloseDialog,
}: Props) {
  const { selectedAddress, handleSelectAddress } = useAddressesContext();
  return (
    <div
      className={cn(
        "overflow-hidden rounded-xl border hover:border-gray border-transparent bg-white p-4 transition",
        selectedAddress?.id === address.id && "border-blue!",
        className,
      )}
      onClick={() => {
        handleSelectAddress(address);
        setTimeout(() => {
          handleCloseDialog?.();
        }, 300);
      }}
    >
      <div className="mb-2 flex items-start gap-3">
        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gray-2">
          {address.address_type === "home" ? (
            <HomeIcon className="size-5" />
          ) : address.address_type === "work" ? (
            <BriefcaseBusinessIcon className="size-5" />
          ) : (
            <MapPinIcon className="size-5" />
          )}
        </div>
        <div className="flex-1">
          <p className="text-base font-bold">{address.address_type}</p>
          <p className="text-light">{address.full_address}</p>
          <Separator className={"my-1.5 w-full h-px"} />
          <div className="flex items-center justify-between gap-2">
            <p className="text-sm text-gray flex items-center font-semibold gap-1">
              {address.recipient_name} , {address.recipient_phone}
              {/* verified icon */}
              {false && (
                <BadgeCheckIcon className="size-5 text-white fill-green" />
              )}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
