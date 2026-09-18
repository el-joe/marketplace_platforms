"use client";
import { createContext, useContext } from "react";
import { useAddresses } from "../hooks/use-addresses";
import { Address } from "../services/address";

interface IAddressesContext {
  addresses: Address[];
  isLoading: boolean;
  selectedAddress: Address | null;
  handleSelectAddress: (address: Address) => void;
}

const addressesContext = createContext<IAddressesContext>({
  addresses: [],
  handleSelectAddress: () => {},
  isLoading: true,
  selectedAddress: null,
});

export const AddressesProvider = ({
  children,
}: {
  children: React.ReactNode;
}) => {
  const { addresses, handleSelectAddress, isLoading, selectedAddress } =
    useAddresses();

  return (
    <addressesContext.Provider
      value={{ addresses, handleSelectAddress, isLoading, selectedAddress }}
    >
      {children}
    </addressesContext.Provider>
  );
};

export const useAddressesContext = () => useContext(addressesContext);
