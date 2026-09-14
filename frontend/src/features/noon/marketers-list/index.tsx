import React from "react";
import Link from "next/link";
import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { MarketerCard } from "./api";

interface Props {
  marketers: MarketerCard[];
  total: number;
  activeType?: string;
}

export default async function MarketersListView({ marketers, total, activeType }: Props) {
  const t = await getTranslations("marketers");

  const TABS: { value?: string; label: string }[] = [
    { value: undefined, label: t("tabAll") },
    { value: "influencer", label: t("tabInfluencers") },
    { value: "affiliate", label: t("tabAffiliates") },
  ];

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="mb-8 text-center">
        <h1 className="text-3xl font-black text-gray-900 mb-2">{t("heading")}</h1>
        <p className="text-gray-500 text-sm">
          {total > 0 ? `${t("countLabel", { count: total })} — ` : ""}
          {t("subtitle")}
        </p>
      </div>

      <div className="flex gap-2 justify-center mb-8">
        {TABS.map((tab) => (
          <Link
            key={tab.label}
            href={tab.value ? `/marketers?type=${tab.value}` : "/marketers"}
            className={`px-4 py-1.5 rounded-full text-sm font-semibold transition ${
              activeType === tab.value
                ? "bg-gray-900 text-white"
                : "bg-gray-100 text-gray-600 hover:bg-gray-200"
            }`}
          >
            {tab.label}
          </Link>
        ))}
      </div>

      {marketers.length === 0 ? (
        <div className="text-center py-24 text-gray-400">{t("emptyState")}</div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
          {marketers.map((marketer) => (
            <Link
              key={marketer.id}
              href={`/marketer/${marketer.profile_slug}`}
              className="group flex flex-col items-center text-center gap-2 p-3 rounded-xl hover:bg-gray-50 transition"
            >
              <div className="relative w-24 h-24 rounded-full overflow-hidden border-2 border-gray-200 group-hover:border-yellow-400 transition">
                {marketer.avatar_url || marketer.banner_url ? (
                  <Image
                    src={marketer.avatar_url ?? marketer.banner_url!}
                    alt={marketer.name}
                    fill
                    className="object-cover"
                  />
                ) : (
                  <div className="w-full h-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-2xl font-black text-white">
                    {marketer.avatar_initial}
                  </div>
                )}
              </div>

              <div>
                <p className="text-sm font-bold text-gray-900 leading-tight line-clamp-1">
                  {marketer.name}
                </p>
                <p className="text-xs text-gray-400 mt-0.5">
                  {marketer.marketer_type === "influencer" ? t("typeInfluencer") : t("typeAffiliate")}
                </p>
              </div>

              {marketer.total_conversions > 0 && (
                <p className="text-xs text-green-600 font-semibold">
                  {t("salesCount", { count: marketer.total_conversions })}
                </p>
              )}
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
