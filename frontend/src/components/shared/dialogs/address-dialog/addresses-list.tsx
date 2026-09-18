import { getAddresses } from "@/src/services/address";
import { useQuery } from "@tanstack/react-query";
import React from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { Skeleton } from "@/src/components/ui/skeleton";
import { useAuthContext } from "@/src/providers/auth-provider";
import AddressCard from "./Address-card";

export default function AddressesList({
  handleCloseDialog,
}: {
  handleCloseDialog?: () => void;
}) {
  const t = useTranslations("header.locationDialog");
  const { isLogged } = useAuthContext();
  const { data, isLoading } = useQuery({
    queryKey: ["addresses"],
    queryFn: getAddresses,
    enabled: isLogged,
  });
  return (
    <div className="overflow-auto w-full min-h-[330px]">
      {isLoading ? (
        <>
          <Skeleton className="h-28" />
          <Skeleton className="h-28 my-4" />
          <Skeleton className="h-28" />
        </>
      ) : !data?.length || !isLogged ? (
        <div className="mx-auto text-center">
          <Image
            src={
              "https://f.nooncdn.com/s/app/com/noon/design-system/empty-states/addressesV2-new.svg"
            }
            width={266}
            height={266}
            alt="empty result"
            className="mx-auto"
          />
          <p className="mb-2 font-bold">{t("noSavedAddresses")}</p>
          <p className="text-secondary max-w-56 text-center mx-auto">
            {t("noSavedAddressesMessage")}
          </p>
        </div>
      ) : (
        <>
          <p className="mb-3 text-light uppercase">{t("savedAddresses")}</p>
          {data?.map((address) => (
            <AddressCard
              key={address.id}
              address={address}
              className="mb-2"
              handleCloseDialog={handleCloseDialog}
            />
          ))}
        </>
      )}
    </div>
  );
}
