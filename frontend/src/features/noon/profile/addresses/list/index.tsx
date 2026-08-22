import { getTranslations } from "next-intl/server";
import AddressCard from "@/src/components/shared/AddressCard";
import VerifyMobileDialog from "@/src/components/shared/dialogs/VerifyMobileDialog";
import type { Address } from "@/src/services/address";
import AddNewAddressTile from "./add-new-address-tile";
import EditAddressTrigger from "./edit-address-trigger";
import DeleteAddressTrigger from "./delete-address-trigger";
import DefaultAddressSlot from "./default-address-slot";

type Props = {
  addresses: Address[];
};

export default async function AddressesList({ addresses }: Props) {
  const t = await getTranslations("profile");

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <AddNewAddressTile label={t("addNewAddress")} />

      {addresses.map((item) => (
        <AddressCard
          key={item.id}
          label={item.label ?? t("addressTab")}
          addressLine={item.full_address || item.street_address}
          receiverName={item.recipient_name}
          receiverPhone={item.recipient_phone}
          verified={false}
          verifyTrigger={
            <VerifyMobileDialog
              phone={item.recipient_phone}
              trigger={
                <button
                  type="button"
                  className="shrink-0 cursor-pointer text-sm font-semibold text-blue-3"
                >
                  {t("verifyNow")}
                </button>
              }
            />
          }
          defaultSlot={
            <DefaultAddressSlot
              addressId={item.id}
              isDefault={item.is_default}
              defaultLabel={t("defaultAddress")}
              setDefaultLabel={t("setDefault")}
            />
          }
          editTrigger={<EditAddressTrigger address={item} label={t("edit")} />}
          deleteTrigger={
            <DeleteAddressTrigger
              addressId={item.id}
              triggerText={t("delete")}
              title={t("deleteAddressConfirmTitle")}
              description={t("deleteAddressConfirmDescription")}
              confirmText={t("deleteAddressConfirmButton")}
              cancelText={t("deleteAddressCancelButton")}
            />
          }
        />
      ))}
    </div>
  );
}
