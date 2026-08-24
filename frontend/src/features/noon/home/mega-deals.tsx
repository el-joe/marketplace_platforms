import CountDown from "@/src/components/shared/CountDown";
import MegaDealsCard from "@/src/components/shared/MegaDealsCard";
import { Button } from "@/src/components/ui/button";
import { Link } from "@/i18n/navigation";
import { getTranslations } from "next-intl/server";
import React from "react";
import { Block } from "./types";
import getLocale from "@/src/helpers/getLocale";
import { cn } from "@/src/lib/utils";

const MegaDeals = async ({ data }: { data: Block }) => {
  const t = await getTranslations("home");
  const locale = await getLocale();
  const dir = locale === "ar" ? "rtl" : "ltr";
  return (
    <div
      className="p-3 pt-9 relative h-full"
      style={{ backgroundColor: data?.background_color as string }}
    >
      {/* countdown */}
      <div className="absolute top-0 left-1/2 -translate-x-1/2 flex items-start">
        <svg
          className={cn(
            "w-3 h-8 text-gray  block",
            dir === "rtl" && " rotate-y-180",
          )}
          viewBox="0 0 12 32"
        >
          <path
            d="M 0 0
                        Q 6 0 6 6
                        L 6 26
                        Q 6 32 12 32
                        V -32
                        Z"
            fill="var(--color-gray)"
          ></path>
        </svg>
        <CountDown targetDate={data?.config?.ends_at as string} />
        <svg
          className={cn(
            "w-3 h-8 text-gray -ms-0.5 block",
            dir === "ltr" && "rotate-y-180",
          )}
          viewBox="0 0 12 32"
        >
          <path
            d="M 0 0
                        Q 6 0 6 6
                        L 6 26
                        Q 6 32 12 32
                        V -32
                        Z"
            fill="var(--color-gray)"
          ></path>
        </svg>
      </div>
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-2xl font-semibold">
          {locale === "ar" ? data?.config?.title_ar : data?.config?.title_en}
        </h3>
        {data?.config?.show_view_all && (
          <Link href={"/"}>
            <Button className={"bg-gray text-white rounded-md capitalize"}>
              {t("allDeals")}
            </Button>
          </Link>
        )}
      </div>
      {/* mega deals cards */}
      <div className="flex gap-4 flex-wrap">
        {data?.products?.map((deal) => (
          <MegaDealsCard key={deal.slug} data={deal} />
        ))}
      </div>
    </div>
  );
};

export default MegaDeals;
