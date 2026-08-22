"use client";

import ConfirmDialog from "@/src/components/shared/dialogs/confirm-dialog/confirm-dialog";
import { useAddressActions } from "../helpers/use-address-actions";

type Props = {
  addressId: string;
  triggerText: string;
  title: string;
  description: string;
  confirmText: string;
  cancelText: string;
};

export default function DeleteAddressTrigger({
  addressId,
  triggerText,
  title,
  description,
  confirmText,
  cancelText,
}: Props) {
  const { deleteAddress } = useAddressActions();

  return (
    <ConfirmDialog
      variant="danger"
      triggerText={triggerText}
      triggerClassName="h-auto px-3 py-1.5 text-xs"
      title={title}
      description={description}
      confirmText={confirmText}
      cancelText={cancelText}
      onConfirm={() => deleteAddress(addressId)}
    />
  );
}
