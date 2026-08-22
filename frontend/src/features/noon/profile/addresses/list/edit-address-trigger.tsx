"use client";

import type { Address } from "@/src/services/address";
import AddAddressModal from "../add-address-modal";
import { useAddressFormActions } from "../helpers/use-address-form-actions";
import {
  toAddressCenter,
  toAddressDetails,
} from "../helpers/to-address-details";

type Props = {
  address: Address;
  label: string;
};

export default function EditAddressTrigger({ address, label }: Props) {
  const { saveExistingAddress } = useAddressFormActions();

  return (
    <AddAddressModal
      initialCenter={toAddressCenter(address)}
      initialDetails={toAddressDetails(address)}
      onSave={(data) => saveExistingAddress(address.id, data)}
      trigger={
        <button
          type="button"
          className="cursor-pointer text-left font-semibold text-blue-3"
        >
          {label}
        </button>
      }
    />
  );
}
