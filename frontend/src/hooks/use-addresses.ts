"use client";
import { useState } from "react";
import { Address, getAddresses } from "../services/address";
import { useQuery } from "@tanstack/react-query";

export const useAddresses = () => {
  const [selectedAddress, setSelectedAddress] = useState<Address | null>(() =>
    JSON.parse(localStorage.getItem("as") as string),
  );
  const { data: addressesData, isLoading } = useQuery({
    queryKey: ["addresses"],
    queryFn: getAddresses,
  });

  const activeAddress =
    selectedAddress ??
    addressesData?.find((address) => address.is_default) ??
    addressesData?.[0] ??
    null;

  const handleSelectAddress = (address: Address) => {
    localStorage.setItem("selectedAddress", JSON.stringify(address));
    setSelectedAddress(address);
  };

  return {
    addresses: addressesData ?? [],
    isLoading,
    selectedAddress: activeAddress,
    handleSelectAddress,
  };
};
