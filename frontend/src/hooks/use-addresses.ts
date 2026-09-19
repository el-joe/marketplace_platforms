"use client";
import { useState } from "react";
import { Address, getAddresses } from "../services/address";
import { useQuery } from "@tanstack/react-query";

export const useAddresses = () => {
  const [selectedAddress, setSelectedAddress] = useState<Address | null>(() => {
    if (typeof window === "undefined") return null; // SSR: no localStorage
    try {
      return JSON.parse(localStorage.getItem("as") as string);
    } catch {
      return null;
    }
  });
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
