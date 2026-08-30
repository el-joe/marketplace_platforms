import Image from "next/image";
import { getLocale, getTranslations } from "next-intl/server";
import Price from "@/src/components/shared/Price";
import { Link } from "@/i18n/navigation";
import { ShieldCheck } from "lucide-react";
import type { OrderDetailItem } from "../helpers/types";
import type { ReactNode } from "react";

type Props = {
  item: OrderDetailItem;
  showItemId?: boolean;
  leading?: ReactNode;
};

export default async function OrderItemRow({
  item,
  showItemId,
  leading,
}: Props) {
  const t = await getTranslations("profile");
  const locale = await getLocale();
  const name = locale === "ar" && item.name_ar ? item.name_ar : item.name_en;

  return (
    <div className="flex items-start gap-4 py-4 not-first:border-t border-border">
      {leading}

      <Image
        src={item.thumbnail ?? "/images/profile/orders-icon.svg"}
        alt={name}
        width={72}
        height={72}
        className="rounded-lg object-cover size-18 shrink-0"
      />

      <div className="flex-1 min-w-0">
        <p className="font-medium text-primary">{name}</p>
        <p className="text-sm text-gray mt-1">
          {t("qtyLabel", { count: item.quantity })}
        </p>
        {!item.can_return && (
          <p className="text-xs text-gray mt-1">
            {t("cannotExchangeOrReturn")}
          </p>
        )}
        {item.can_claim_warranty && (
          <Link
            href={`/warranty-claims/create?order_item_id=${item.id}`}
            className="text-xs font-medium text-blue-600 hover:underline flex items-center gap-1 mt-1"
          >
            <ShieldCheck className="size-3.5" />
            {t("fileWarrantyClaim")}
          </Link>
        )}
        <p className="font-bold mt-2">
          <Price currentPrice={item.line_total} size="sm" />
        </p>
      </div>

      {showItemId && (
        <div className="flex flex-col items-end gap-2 shrink-0">
          <span className="text-xs text-gray">
            {t("itemIdLabel")} {item.id}
          </span>
        </div>
      )}
    </div>
  );
}
