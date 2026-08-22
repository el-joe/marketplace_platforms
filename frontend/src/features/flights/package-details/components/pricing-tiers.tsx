import { getTranslations } from "next-intl/server";
import { UsersIcon } from "lucide-react";
import Price from "@/src/components/shared/Price";

type Tier = { travelers_count: number; price: number };

type Props = {
  tiers: Tier[];
  currency: string;
};

export default async function PricingTiers({ tiers, currency }: Props) {
  const t = await getTranslations("flights.packageDetails");

  return (
    <div>
      <h3 className="text-sm font-bold mb-3 text-primary flex items-center gap-2">
        <UsersIcon className="size-4 text-blue-3" />
        {t("groupPricingTitle")}
      </h3>
      <div className="grid grid-cols-2 gap-2">
        {tiers.map((tier) => (
          <div
            key={tier.travelers_count}
            className="bg-blue-3/5 border border-blue-3/20 rounded-xl px-3 py-2.5 text-center"
          >
            <p className="text-xs text-gray mb-1">
              {t("travelersCount", { count: tier.travelers_count })}
            </p>
            <Price currentPrice={tier.price} currency={currency} size="sm" />
          </div>
        ))}
      </div>
    </div>
  );
}
