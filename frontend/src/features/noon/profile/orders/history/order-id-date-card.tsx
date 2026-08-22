import { getTranslations } from "next-intl/server";
import { format } from "date-fns";
import Card from "@/src/components/shared/Card";

type Props = {
  orderId: string;
  orderDate: string;
  idLabel?: string;
};

export default async function OrderIdDateCard({
  orderId,
  orderDate,
  idLabel,
}: Props) {
  const t = await getTranslations("profile");

  return (
    <Card className="flex items-center justify-between border border-border p-6">
      <p className="font-medium">
        {idLabel ?? t("orderIdPrefix")}{" "}
        <span className="font-bold">{orderId}</span>
      </p>
      <p className="text-sm text-gray">
        {t("orderDatePrefix")} {format(new Date(orderDate), "d MMM yyyy")}
      </p>
    </Card>
  );
}
