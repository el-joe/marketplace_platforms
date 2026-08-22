import { getLocale, getTranslations } from "next-intl/server";
import { ArrowLeftIcon, ArrowRightIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import CancelItemsForm from "./components/cancel-items-form";
import type { OrderDetail } from "../helpers/types";

type Props = {
  order: OrderDetail;
};

export default async function CancelItems({ order }: Props) {
  const t = await getTranslations("profile");
  const locale = await getLocale();
  const items = order.sub_orders.flatMap((subOrder) => subOrder.items);

  return (
    <div>
      <Link
        href={`/orders/${order.order_number}`}
        className="flex items-center gap-2 text-sm text-gray"
      >
        {locale === "ar" ? (
          <ArrowRightIcon className="size-4" />
        ) : (
          <ArrowLeftIcon className="size-4" />
        )}
        {t("backToOrderDetails")}
      </Link>

      <h1 className="mt-3 text-[28px] font-bold">{t("cancelItems")}</h1>
      <p className="mt-1 text-sm text-gray">{t("cancelItemsSubtitle")}</p>

      <div className="mt-6">
        <CancelItemsForm orderNumber={order.order_number} items={items} />
      </div>
    </div>
  );
}
