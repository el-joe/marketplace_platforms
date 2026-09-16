import { getTranslations } from "next-intl/server";
import { MapPin, ShoppingBag, TrendingUp } from "lucide-react";
import { MarketerProfileInfo, MarketerProfileMarketer } from "../helpers/types";

interface Props {
  marketer: MarketerProfileMarketer;
  profile: MarketerProfileInfo;
}

const SOCIAL_ICONS: Record<string, string> = {
  instagram: "📸",
  tiktok: "🎵",
  youtube: "▶️",
  twitter: "𝕏",
  snapchat: "👻",
  facebook: "👤",
};

export default async function MarketerProfileSidebar({
  marketer,
  profile,
}: Props) {
  const t = await getTranslations("marketerProfile");

  return (
    <aside className="w-full lg:w-72 shrink-0 space-y-6">
      <div className="grid grid-cols-2 gap-3">
        <div className="rounded-2xl border border-border-color bg-gray-5 p-4 text-center">
          <ShoppingBag className="mx-auto h-4 w-4 text-gray" />
          <div className="mt-1.5 text-2xl font-black text-primary">
            {marketer.total_campaigns}
          </div>
          <div className="mt-0.5 text-xs font-semibold text-gray">{t("campaigns")}</div>
        </div>
        <div className="rounded-2xl border border-border-color bg-light-green p-4 text-center">
          <TrendingUp className="mx-auto h-4 w-4 text-green" />
          <div className="mt-1.5 text-2xl font-black text-green">
            {marketer.total_conversions}
          </div>
          <div className="mt-0.5 text-xs font-semibold text-green">{t("sales")}</div>
        </div>
      </div>

      {(profile.bio_ar || profile.bio_en) && (
        <div className="space-y-1.5">
          <h3 className="text-sm font-bold text-primary">{t("bio")}</h3>
          <p className="text-sm leading-relaxed text-light">
            {profile.bio_ar ?? profile.bio_en}
          </p>
        </div>
      )}

      {marketer.country && (
        <div className="inline-flex items-center gap-1.5 rounded-full bg-gray-2 px-3 py-1.5 text-xs font-semibold text-light">
          <MapPin className="h-3.5 w-3.5" />
          {marketer.country.name_ar}
        </div>
      )}

      {Object.keys(profile.social_links ?? {}).length > 0 && (
        <div className="space-y-2">
          <h3 className="text-sm font-bold text-primary">
            {t("socialMedia")}
          </h3>
          <div className="flex flex-wrap gap-2">
            {Object.entries(profile.social_links).map(([platform, url]) =>
              url ? (
                <a
                  key={platform}
                  href={url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-1.5 rounded-full bg-gray-2 px-3 py-1.5 text-xs font-medium text-light transition hover:bg-gray-4"
                >
                  <span>{SOCIAL_ICONS[platform] ?? "🔗"}</span>
                  <span className="capitalize">{platform}</span>
                </a>
              ) : null,
            )}
          </div>
        </div>
      )}

      {profile.video_url && (
        <div>
          <h3 className="mb-2 text-sm font-bold text-primary">{t("video")}</h3>
          <div className="aspect-video overflow-hidden rounded-2xl bg-gray-2">
            <iframe
              src={profile.video_url.replace("watch?v=", "embed/")}
              className="h-full w-full"
              allowFullScreen
              title={t("videoIframeTitle")}
            />
          </div>
        </div>
      )}
    </aside>
  );
}
