import { getTranslations } from "next-intl/server";
import type { MarketerBanner } from "../helpers/types";
import BannerImage from "./banner-image";
import BannerLink from "./banner-link";

export default async function AdStrip({ banners }: { banners: MarketerBanner[] }) {
  if (banners.length === 0) return null;
  const t = await getTranslations("marketers");
  const shown = banners.slice(0, 2);

  return (
    <section aria-label={t("adStripLabel")} className="mt-10">
      <div className={`grid grid-cols-1 gap-4 ${shown.length > 1 ? "md:grid-cols-2" : ""}`}>
        {shown.map((banner) => (
          <BannerLink
            key={banner.id}
            href={banner.link}
            clickUrl={banner.click_url}
            ariaLabel={banner.title ?? undefined}
            className="relative block aspect-[840/300] overflow-hidden rounded-xl"
          >
            <BannerImage banner={banner} sizes="(min-width:768px) 50vw, 100vw" />
          </BannerLink>
        ))}
      </div>
    </section>
  );
}
