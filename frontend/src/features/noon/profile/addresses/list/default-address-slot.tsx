"use client";

import { useAddressActions } from "../helpers/use-address-actions";

type Props = {
  addressId: string;
  isDefault: boolean;
  defaultLabel: string;
  setDefaultLabel: string;
};

export default function DefaultAddressSlot({
  addressId,
  isDefault,
  defaultLabel,
  setDefaultLabel,
}: Props) {
  const { setDefaultAddress } = useAddressActions();

  if (isDefault) {
    return <span className="text-sm font-semibold text-gray">{defaultLabel}</span>;
  }

  return (
    <button
      type="button"
      onClick={() => setDefaultAddress(addressId)}
      className="cursor-pointer text-sm font-semibold text-blue-3"
    >
      {setDefaultLabel}
    </button>
  );
}
