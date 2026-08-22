import { getTranslations } from "next-intl/server";
import OrderGroupCard from "@/src/components/shared/order-group-card";
import Card from "@/src/components/shared/Card";
import {
  getReturnStatusLabelKey,
  isNegativeReturnStatus,
} from "../helpers/to-return-status";
import type { ReturnListItem } from "../../orders/helpers/types";

type Props = {
  returns: ReturnListItem[];
};

export default async function ReturnsList({ returns }: Props) {
  const t = await getTranslations("profile");

  return (
    <Card className="mt-6 p-6">
      <div className="flex flex-col gap-4">
        {returns.map((returnRequest) => {
          const labelKey = getReturnStatusLabelKey(returnRequest.status);

          return (
            <OrderGroupCard
              key={returnRequest.return_number}
              title={labelKey ? t(labelKey) : returnRequest.status}
              isNegative={isNegativeReturnStatus(returnRequest.status)}
              items={returnRequest.items.map((line) => ({
                id: line.order_item_id,
                label:
                  line.product_snapshot.name_en ??
                  line.product_snapshot.sku ??
                  line.order_item_id,
                amount:
                  line.product_snapshot.line_total ??
                  line.product_snapshot.unit_price ??
                  0,
                href: `/returns/${returnRequest.return_number}`,
              }))}
              footer={t("returnIdLabel", { id: returnRequest.return_number })}
            />
          );
        })}
      </div>
    </Card>
  );
}
