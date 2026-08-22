import Image from "next/image";
import { CheckIcon, CircleSlashIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import Card from "@/src/components/shared/Card";
import { Badge } from "@/src/components/ui/badge";
import Price from "@/src/components/shared/Price";

export type OrderGroupCardItem = {
  id: string;
  label: string;
  amount: number;
  href: string;
};

type Props = {
  title: string;
  isNegative: boolean;
  badgeLabel?: string;
  items: OrderGroupCardItem[];
  footer: string;
};

/**
 * Shared summary card for a group of line items under one heading — used by
 * both the orders list (grouped by order) and the returns list (grouped by
 * return request). Callers translate/derive `title`/`isNegative`/`badgeLabel`
 * from their own domain status before rendering this, since order status and
 * return status are different enums.
 */
export default function OrderGroupCard({
  title,
  isNegative,
  badgeLabel,
  items,
  footer,
}: Props) {
  return (
    <Card className="border border-border p-6">
      <div className="flex items-center gap-3">
        <span
          className={`flex size-6 items-center justify-center rounded-full ${
            isNegative ? "bg-light-red text-red" : "bg-light-green text-green"
          }`}
        >
          {isNegative ? (
            <CircleSlashIcon className="size-3.5" />
          ) : (
            <CheckIcon className="size-3.5" />
          )}
        </span>

        <h2 className="font-bold text-lg text-light">{title}</h2>

        {badgeLabel && <Badge variant="green">{badgeLabel}</Badge>}
      </div>

      <div className="flex items-center justify-between mt-4 border-t border-border">
        {items.map((item) => (
          <Link
            key={item.id}
            href={item.href}
            className="flex items-center gap-4 py-4"
          >
            <Image
              src="/images/profile/orders-icon.svg"
              alt={item.label}
              width={64}
              height={64}
              className="size-16 shrink-0 rounded-lg object-cover"
            />
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium text-primary">
                {item.label}
              </p>
              <Price currentPrice={item.amount} size="sm" className="mt-1" />
            </div>
          </Link>
        ))}
      </div>

      <p className="text-right text-xs text-gray">{footer}</p>
    </Card>
  );
}
