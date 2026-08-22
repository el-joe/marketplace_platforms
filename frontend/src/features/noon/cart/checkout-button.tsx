"use client";
import { useRouter } from "@/i18n/navigation";
import LocationDialog from "@/src/components/shared/dialogs/LocationDialog";
import { Button } from "@/src/components/ui/button";
import { Spinner } from "@/src/components/ui/spinner";
import { useAuthContext } from "@/src/providers/auth-provider";
import { getAddresses } from "@/src/services/address";
import { useTranslations } from "next-intl";
import React, { useState } from "react";

export default function CheckoutButton() {
  const t = useTranslations("cart");
  const [locationDialogOpen, setLocationDialogOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const { protectedWithAuth } = useAuthContext();
  const router = useRouter();
  return (
    <>
      <Button
        className={"bg-blue w-full text-white h-15 rounded-[16px] text-xl"}
        disabled={loading}
        onClick={() =>
          protectedWithAuth(async () => {
            setLoading(true);
            const address = await getAddresses();
            if (!!address.length) {
              router.push("/checkout");
            } else {
              setLocationDialogOpen(true);
            }
            setLoading(false);
          })
        }
      >
        {loading ? <Spinner /> : t("checkout")}
      </Button>
      <LocationDialog
        open={locationDialogOpen}
        onClose={() => setLocationDialogOpen(false)}
      />
    </>
  );
}
