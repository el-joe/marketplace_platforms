import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { Sparkles } from "lucide-react";
import { MarketerCard } from "../api";

interface Props {
  marketers: MarketerCard[];
  total: number;
}

export default async function MarketersHero({ marketers, total }: Props) {
  const t = await getTranslations("marketers");

  const totalConversions = marketers.reduce((sum, m) => sum + m.total_conversions, 0);
  const totalCampaigns = marketers.reduce((sum, m) => sum + m.total_campaigns, 0);

  const marqueeItems = marketers.slice(0, 14);
  const marqueeTrack = [...marqueeItems, ...marqueeItems];

  return (
    <div className="relative overflow-hidden rounded-[2rem] bg-neutral-950 pt-14 sm:pt-20">
      <div className="pointer-events-none absolute -top-24 left-1/4 h-72 w-72 rounded-full bg-main/25 blur-[100px]" />
      <div className="pointer-events-none absolute top-10 right-0 h-64 w-64 rounded-full bg-fuchsia-500/20 blur-[100px]" />
      <div className="pointer-events-none absolute bottom-0 left-0 h-56 w-56 rounded-full bg-sky-500/20 blur-[100px]" />
      <div
        className="pointer-events-none absolute inset-0 opacity-[0.07]"
        style={{
          backgroundImage:
            "linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px)",
          backgroundSize: "42px 42px",
        }}
      />

      <div className="relative px-6 text-center sm:px-12">
        <span className="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3.5 py-1.5 text-xs font-bold text-main backdrop-blur-sm">
          <Sparkles className="h-3.5 w-3.5" />
          {t("subtitle")}
        </span>

        <h1 className="mx-auto mt-5 max-w-2xl text-4xl font-black tracking-tight text-white sm:text-6xl">
          {t("heading")}
        </h1>

        <p className="mx-auto mt-4 max-w-lg text-sm font-medium text-white/50 sm:text-base">
          {t("subtitle")}
        </p>

        {total > 0 && (
          <div className="mx-auto mt-8 flex w-fit flex-wrap items-center justify-center gap-6 rounded-2xl border border-white/10 bg-white/[0.04] px-6 py-4 backdrop-blur-sm sm:gap-10">
            <Stat value={total} label={t("statMarketers")} />
            <div className="h-8 w-px bg-white/10" />
            <Stat value={totalCampaigns} label={t("statCampaigns")} />
            <div className="h-8 w-px bg-white/10" />
            <Stat value={totalConversions} label={t("statSales")} />
          </div>
        )}
      </div>

      {marqueeItems.length > 0 && (
        <div className="relative mt-10 [mask-image:linear-gradient(to_right,transparent,black_12%,black_88%,transparent)] sm:mt-14">
          <div className="flex w-max animate-marquee items-center gap-4 pb-10">
            {marqueeTrack.map((marketer, i) => (
              <div
                key={`${marketer.id}-${i}`}
                className="relative h-14 w-14 shrink-0 overflow-hidden rounded-full ring-2 ring-white/10 sm:h-16 sm:w-16"
              >
                {marketer.avatar_url ? (
                  <Image src={marketer.avatar_url} alt="" fill className="object-cover" />
                ) : (
                  <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-amber-400 to-amber-600 text-lg font-black text-white">
                    {marketer.avatar_initial}
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function Stat({ value, label }: { value: number; label: string }) {
  return (
    <div className="flex flex-col items-center">
      <span className="text-xl font-black text-white sm:text-2xl">{value.toLocaleString()}</span>
      <span className="mt-0.5 text-[11px] font-semibold text-white/40">{label}</span>
    </div>
  );
}
