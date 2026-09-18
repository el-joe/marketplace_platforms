"use client";
import LocationDialog from "@/src/components/shared/dialogs/address-dialog/address-dialog";
import { Button } from "@/src/components/ui/button";
import { Building2Icon, HomeIcon, MapPinIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import React from "react";
import { IPrepareCheckout } from "./types/checkout.type";
import { useAddressesContext } from "@/src/providers/addresses-provider";

export default function AddressCard({
  addressId,
}: {
  addressId: IPrepareCheckout["address"]["id"];
}) {
  const t = useTranslations("checkout");

  const { addresses } = useAddressesContext();
  const selectedAddress = addresses?.find((a) => `${a.id}` === `${addressId}`);
  return (
    <div className="p-3 rounded-2xl bg-white flex gap-3 items-center">
      <div className="bg-gray-2 text-black min-w-10 h-10 rounded-lg grid place-items-center">
        {selectedAddress?.address_type === "home" ? (
          <HomeIcon className="size-4" />
        ) : selectedAddress?.address_type === "work" ? (
          <Building2Icon className="size-4" />
        ) : (
          <MapPinIcon className="size-4" />
        )}
      </div>
      <div>
        <h4 className="text-base font-semibold">
          {t("deliverTo")} {selectedAddress?.address_type}
        </h4>
        <p className="text-gray text-sm line-clamp-1">
          {selectedAddress?.full_address}
        </p>
      </div>
      <LocationDialog
        triggerButton={
          <Button
            variant={"ghost"}
            className={
              "bg-transparent text-blue-2 text-base ms-auto font-semibold"
            }
          >
            {t("editAddress")}
          </Button>
        }
      />
    </div>
  );
}
