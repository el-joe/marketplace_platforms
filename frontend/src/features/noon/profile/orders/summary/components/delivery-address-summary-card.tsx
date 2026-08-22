import { getTranslations } from "next-intl/server";
import { format } from "date-fns";
import { InfoIcon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import { Badge } from "@/src/components/ui/badge";
import type { OrderDetail } from "../../helpers/types";

type Props = {
  address: OrderDetail["shipping_address"];
  estimatedDeliveryDate: string | null;
};

export default async function DeliveryAddressSummaryCard({
  address,
  estimatedDeliveryDate,
}: Props) {
  const t = await getTranslations("profile");

  return (
    <Card className="border border-border p-6">
      <h2 className="font-bold text-lg">{t("deliveryAddressLabel")}</h2>

      <div className="mt-4 text-sm">
        <p className="font-medium text-primary">{address.recipient_name}</p>
        <p className="mt-1 text-gray">
          {address.street_address}, {address.area}, {address.city},{" "}
          {address.country}
        </p>
        <p className="mt-1 text-gray">{address.recipient_phone}</p>
      </div>

      {estimatedDeliveryDate && (
        <Badge variant="gray" className="mt-4">
          {t("estimatedDeliveryBy", {
            date: format(new Date(estimatedDeliveryDate), "d MMM"),
          })}
          <InfoIcon className="size-3.5" />
        </Badge>
      )}
    </Card>
  );
}
