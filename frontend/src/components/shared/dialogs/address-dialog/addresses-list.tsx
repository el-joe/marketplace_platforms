import { getAddresses } from "@/src/services/address";
import { useQuery } from "@tanstack/react-query";
import React from "react";
import AddressCard from "../../AddressCard";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { Skeleton } from "@/src/components/ui/skeleton";

export default function AddressesList() {
  const t = useTranslations("header.locationDialog");
  const { data, isPending } = useQuery({
    queryKey: ["addresses"],
    queryFn: getAddresses,
  });
  return (
    <div className="flex flex-col overflow-auto w-full gap-2">
      {}
      {isPending ? (
        <>
          <Skeleton className="h-28" />
          <Skeleton className="h-28 my-4" />
          <Skeleton className="h-28" />
        </>
      ) : !data?.length ? (
        <div className="mx-auto text-center">
          <Image
            src={
              "https://f.nooncdn.com/s/app/com/noon/design-system/empty-states/addressesV2-new.svg"
            }
            width={266}
            height={266}
            alt="empty result mx-auto"
          />
          <p className="mb-2 font-bold">{t("noSavedAddresses")}</p>
          <p className="text-secondary max-w-56 text-center mx-auto">
            {t("noSavedAddressesMessage")}
          </p>
        </div>
      ) : (
        data?.map((address) => (
          <AddressCard
            key={address.id}
            label={address.label}
            addressLine={address.full_address || address.street_address}
            receiverName={address.recipient_name}
            receiverPhone={address.recipient_phone}
            verified={false}
          />
        ))
      )}
    </div>
  );
}
