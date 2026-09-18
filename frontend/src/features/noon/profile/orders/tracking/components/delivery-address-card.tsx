import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import Card from "@/src/components/shared/Card";
import { Button } from "@/src/components/ui/button";
import { hasShippedOrLater } from "../../helpers/to-order-status";
import type { OrderDetail, OrderStatus } from "../../helpers/types";

type Props = {
  address: OrderDetail["shipping_address"];
  status: OrderStatus;
  isNegative: boolean;
};

export default async function DeliveryAddressCard({
  address,
  status,
  isNegative,
}: Props) {
  const t = await getTranslations("profile");

  // Once the order has shipped (or is further along), the delivery address
  // is locked in — there's no address to update anymore.
  const canUpdateAddress = !isNegative && !hasShippedOrLater(status);

  return (
    <Card className="border border-border p-6">
      <div className="flex items-center justify-between">
        <h2 className="font-bold text-lg">{t("deliveryAddressLabel")}</h2>

        {canUpdateAddress && (
          <Button
            render={<Link href="/addresses" />}
            nativeButton={false}
            variant="outline"
            className="font-semibold"
          >
            {t("updateAddress")}
          </Button>
        )}
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
