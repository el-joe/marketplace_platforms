"use client";

import { PlusIcon } from "lucide-react";
import AddAddressModal from "../add-address-modal";
import { useAddressFormActions } from "../helpers/use-address-form-actions";

type Props = {
  label: string;
};

export default function AddNewAddressTile({ label }: Props) {
  const { saveNewAddress } = useAddressFormActions();

  return (
    <AddAddressModal
      onSave={saveNewAddress}
      trigger={
        <button
          type="button"
          className="flex h-40 w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-blue-3/40 text-blue-3 cursor-pointer"
        >
          <PlusIcon className="size-5" />
          <span className="font-semibold">{label}</span>
        </button>
      }
    />
  );
}
