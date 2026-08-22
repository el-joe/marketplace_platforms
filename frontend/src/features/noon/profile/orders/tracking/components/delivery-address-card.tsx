import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import Card from "@/src/components/shared/Card";
import { Button } from "@/src/components/ui/button";
import type { OrderDetail } from "../../helpers/types";

type Props = {
  address: OrderDetail["shipping_address"];
};

export default async function DeliveryAddressCard({ address }: Props) {
  const t = await getTranslations("profile");

  return (
    <Card className="border border-border p-6">
      <div className="flex items-center justify-between">
        <h2 className="font-bold text-lg">{t("deliveryAddressLabel")}</h2>

        <Button
          render={<Link href="/addresses" />}
          nativeButton={false}
          variant="outline"
          className="font-semibold"
        >
          {t("updateAddress")}
        </Button>
      </div>

      <div className="mt-4 text-sm">
        <p className="font-medium text-primary">{address.recipient_name}</p>
        <p className="mt-1 text-gray">
          {address.street_address}, {address.area}, {address.city},{" "}
          {address.country}
        </p>
        <p className="mt-1 text-gray">{address.recipient_phone}</p>
      </div>
    </Card>
  );
}
