"use client";
import LocationDialog from "@/src/components/shared/dialogs/LocationDialog";
import { Button } from "@/src/components/ui/button";
import { getAddresses } from "@/src/services/address";
import { useQuery } from "@tanstack/react-query";
import { Building2Icon, HomeIcon, MapPinIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import React from "react";
import { IPrepareCheckout } from "./types/checkout.type";

export default function AddressCard({
  addressId,
}: {
  addressId: IPrepareCheckout["address"]["id"];
}) {
  const t = useTranslations("checkout");
  const addresses = useQuery({
    queryKey: ["addresses"],
    queryFn: getAddresses,
  });
  const selectedAddress = addresses.data?.find(
    (a) => `${a.id}` === `${addressId}`,
  );
  return (
    <div className="p-3 rounded-2xl bg-white flex gap-3 items-center">
      <div className="bg-gray-2 text-black min-w-10 h-10 rounded-lg grid place-items-center">
        {selectedAddress?.label === "home" ? (
          <HomeIcon className="size-4" />
        ) : selectedAddress?.label === "work" ? (
          <Building2Icon className="size-4" />
        ) : (
          <MapPinIcon className="size-4" />
        )}
      </div>
      <div>
        <h4 className="text-base font-semibold">
          {t("deliverTo")} {selectedAddress?.label}
        </h4>
        <p className="text-gray text-sm line-clamp-1">
          {selectedAddress?.full_address}
        </p>
      </div>
      <LocationDialog
        triggerButton={
          <Button
            variant={"ghost"}
            className={"bg-transparent text-blue-2 text-base ms-auto"}
          >
            {t("editAddress")}
          </Button>
        }
      />
    </div>
  );
}
