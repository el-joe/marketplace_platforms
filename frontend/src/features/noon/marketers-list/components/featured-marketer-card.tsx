import Link from "next/link";
import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { BadgeCheck, ShoppingBag, TrendingUp } from "lucide-react";
import { MarketerCard } from "../api";

interface Props {
  marketer: MarketerCard;
}

export default async function FeaturedMarketerCard({ marketer }: Props) {
  const t = await getTranslations("marketers");

  return (
    <Link
      href={`/marketer/${marketer.profile_slug}`}
      className="group relative col-span-2 row-span-2 flex flex-col justify-end overflow-hidden rounded-3xl bg-neutral-900 sm:min-h-[22rem]"
    >
      {marketer.banner_url ? (
        <Image
          src={marketer.banner_url}
          alt=""
          fill
          className="object-cover opacity-70 transition-transform duration-700 group-hover:scale-105"
        />
      ) : (
        <div className="absolute inset-0 bg-gradient-to-br from-amber-500/40 via-neutral-900 to-neutral-950" />
      )}
      <div className="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent" />

      <span className="absolute top-4 ltr:left-4 rtl:right-4 inline-flex items-center gap-1.5 rounded-full bg-main px-3 py-1 text-[11px] font-black text-primary shadow-lg">
        <BadgeCheck className="h-3.5 w-3.5" />
        {t("featuredBadge")}
      </span>

      <div className="relative flex items-center gap-4 p-5 sm:p-7">
        <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-full ring-4 ring-white/90 sm:h-20 sm:w-20">
          {marketer.avatar_url ? (
            <Image src={marketer.avatar_url} alt={marketer.name} fill className="object-cover" />
          ) : (
            <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-amber-400 to-amber-600 text-2xl font-black text-white">
              {marketer.avatar_initial}
            </div>
          )}
        </div>

        <div className="min-w-0">
          <p className="truncate text-lg font-black text-white sm:text-2xl">{marketer.name}</p>
          <div className="mt-2 flex flex-wrap items-center gap-3 text-xs font-bold text-white/70 sm:text-sm">
            {marketer.total_campaigns > 0 && (
              <span className="inline-flex items-center gap-1.5">
                <ShoppingBag className="h-4 w-4" />
                {marketer.total_campaigns} {t("statCampaigns")}
              </span>
            )}
            {marketer.total_conversions > 0 && (
              <span className="inline-flex items-center gap-1.5 text-main">
                <TrendingUp className="h-4 w-4" />
                {t("salesCount", { count: marketer.total_conversions })}
              </span>
            )}
          </div>
        </div>
      </div>
    </Link>
  );
}
