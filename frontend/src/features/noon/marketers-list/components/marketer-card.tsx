import Link from "next/link";
import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { ArrowUpRight, Clapperboard, Link2, ShoppingBag, TrendingUp } from "lucide-react";
import { MarketerCard as MarketerCardData } from "../api";
import { CARD_GRADIENTS, CARD_GLOWS } from "../helpers/constants";

interface Props {
  marketer: MarketerCardData;
  index: number;
}

export default async function MarketerCard({ marketer, index }: Props) {
  const t = await getTranslations("marketers");
  const isInfluencer = marketer.marketer_type === "influencer";
  const gradient = CARD_GRADIENTS[index % CARD_GRADIENTS.length];
  const glow = CARD_GLOWS[index % CARD_GLOWS.length];

  return (
    <Link
      href={`/marketer/${marketer.profile_slug}`}
      style={{ "--card-glow": glow } as React.CSSProperties}
      className="group relative flex flex-col overflow-hidden rounded-2xl border border-border-color bg-white transition-all duration-300 hover:-translate-y-1.5 hover:border-transparent hover:shadow-[0_22px_45px_-14px_var(--card-glow)]"
    >
      <div className="relative h-28 w-full overflow-hidden">
        {marketer.banner_url ? (
          <Image
            src={marketer.banner_url}
            alt=""
            fill
            className="object-cover transition-transform duration-500 group-hover:scale-110"
          />
        ) : (
          <div className={`relative h-full w-full bg-gradient-to-br ${gradient} transition-transform duration-500 group-hover:scale-110`}>
            <div className="absolute inset-0 opacity-25 [background-image:repeating-linear-gradient(135deg,white_0,white_1px,transparent_1px,transparent_10px)]" />
          </div>
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-black/35 via-black/0 to-black/10" />

        <span
          className={`absolute top-2.5 ltr:right-2.5 rtl:left-2.5 inline-flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-bold backdrop-blur-md ${
            isInfluencer ? "bg-purple-950/60 text-purple-100" : "bg-blue-950/60 text-blue-100"
          }`}
        >
          {isInfluencer ? (
            <Clapperboard className="h-2.5 w-2.5" />
          ) : (
            <Link2 className="h-2.5 w-2.5" />
          )}
          {isInfluencer ? t("typeInfluencer") : t("typeAffiliate")}
        </span>
      </div>

      <div className="flex flex-col items-center px-3 pb-4 text-center">
        <div className={`relative -mt-10 h-20 w-20 shrink-0 rounded-full bg-gradient-to-br ${gradient} p-0.75 shadow-lg`}>
          <div className="h-full w-full rounded-full bg-white p-0.75">
            <div className="relative h-full w-full overflow-hidden rounded-full">
              {marketer.avatar_url ? (
                <Image src={marketer.avatar_url} alt={marketer.name} fill className="object-cover" />
              ) : (
                <div className={`flex h-full w-full items-center justify-center bg-gradient-to-br ${gradient} text-xl font-black text-white`}>
                  {marketer.avatar_initial}
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="mt-2.5 flex items-center gap-1 text-primary">
          <p className="line-clamp-1 text-sm font-bold">{marketer.name}</p>
          <ArrowUpRight className="h-3.5 w-3.5 shrink-0 -translate-x-1 opacity-0 transition-all duration-300 group-hover:translate-x-0 group-hover:opacity-100" />
        </div>

        <div className="mt-3 flex flex-wrap items-center justify-center gap-1.5">
          {marketer.total_campaigns > 0 && (
            <span className="inline-flex items-center gap-1 rounded-full bg-gray-2 px-2.5 py-1 text-[11px] font-bold text-light">
              <ShoppingBag className="h-3 w-3" />
              {marketer.total_campaigns}
            </span>
          )}
          {marketer.total_conversions > 0 && (
            <span className="inline-flex items-center gap-1 rounded-full bg-light-green px-2.5 py-1 text-[11px] font-bold text-green">
              <TrendingUp className="h-3 w-3" />
              {t("salesCount", { count: marketer.total_conversions })}
            </span>
          )}
        </div>
      </div>
    </Link>
  );
}
