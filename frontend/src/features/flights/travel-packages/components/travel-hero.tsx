import Image from "next/image";
import { PlaneIcon } from "lucide-react";
import { getTranslations } from "next-intl/server";
import type { TravelPackageSummary } from "../../helpers/types";

type Props = {
  featuredPackage?: TravelPackageSummary;
};

export default async function TravelHero({ featuredPackage }: Props) {
  const t = await getTranslations("flights");

  return (
    <div className="relative w-full h-64 md:h-80 overflow-hidden rounded-2xl bg-gradient-to-br from-blue-3 to-blue">
      {featuredPackage?.thumbnail && (
        <Image
          src={featuredPackage.thumbnail}
          alt={featuredPackage.destination_city}
          fill
          className="object-cover opacity-40"
          priority
          sizes="100vw"
        />
      )}

      <div className="absolute inset-0 bg-gradient-to-t from-blue-3/80 via-blue-3/30 to-transparent" />

      <div className="absolute inset-0 flex flex-col items-center justify-center text-white text-center px-6">
        <div className="flex items-center gap-2 mb-3">
          <PlaneIcon className="size-6" />
          <span className="text-sm font-medium uppercase tracking-widest opacity-80">
            {t("heroEyebrow")}
          </span>
        </div>
        <h1 className="text-3xl md:text-4xl font-bold mb-2">{t("heroTitle")}</h1>
        <p className="text-sm opacity-80 max-w-md">{t("heroSubtitle")}</p>
      </div>
    </div>
  );
}
