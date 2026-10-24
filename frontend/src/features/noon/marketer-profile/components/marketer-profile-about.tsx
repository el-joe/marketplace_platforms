import { getTranslations } from "next-intl/server";
import { MapPin } from "lucide-react";
import getLocale from "@/src/helpers/getLocale";
import type { MarketerProfileInfo, MarketerProfileMarketer } from "../helpers/types";

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

/** Bio, country, contact, social, broker specialization and video (formerly the sidebar). */
export default async function MarketerProfileAbout({ marketer, profile }: Props) {
  const t = await getTranslations("marketerProfile");
  const locale = await getLocale();
  const isAr = locale === "ar";

  const bio = (isAr ? profile.bio_ar : profile.bio_en) ?? profile.bio_ar ?? profile.bio_en;
  const social = Object.entries(profile.social_links ?? {}).filter(([, url]) => !!url);
  const contacts = Object.entries(profile.contact_details ?? {}).filter(([, v]) => !!v);
  const spec = profile.broker_specialization;
  const cityName = spec ? (isAr ? spec.city_name_ar : spec.city_name_en) ?? spec.city_name_ar ?? spec.city_name_en : null;
  const specCategories = (spec?.categories ?? [])
    .map((c) => (isAr ? c.category_name_ar : c.category_name_en) ?? c.category_name_ar ?? c.category_name_en)
    .filter(Boolean) as string[];

  const hasAny = bio || marketer.country || social.length || contacts.length || spec || profile.video_url;
  if (!hasAny) return null;

  return (
    <section className="rounded-xl border border-border-color bg-gray-5 p-4 md:p-5">
      <div className="flex flex-col gap-4 md:flex-row md:gap-8">
        <div className="min-w-0 flex-1 space-y-3">
          {bio && (
            <div>
              <h2 className="mb-1 text-sm font-bold text-primary">{t("bio")}</h2>
              <p className="text-sm leading-relaxed text-light">{bio}</p>
            </div>
          )}

          <div className="flex flex-wrap gap-2">
            {marketer.country && (
              <span className="inline-flex items-center gap-1.5 rounded-full bg-gray-2 px-3 py-1.5 text-xs font-semibold text-light">
                <MapPin className="size-3.5" />
                {isAr ? marketer.country.name_ar : marketer.country.name_en}
              </span>
            )}
            {social.map(([platform, url]) => (
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
            ))}
          </div>

          {contacts.length > 0 && (
            <div className="space-y-1">
              <h3 className="text-sm font-bold text-primary">{t("contact")}</h3>
              <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-light">
                {contacts.map(([k, v]) => (
                  <li key={k}>
                    <span className="capitalize">{k}</span>: <span dir="ltr">{v}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}

          {spec && (specCategories.length > 0 || cityName || spec.serves_all_cities) && (
            <div className="space-y-1">
              <h3 className="text-sm font-bold text-primary">{t("specialization")}</h3>
              <div className="flex flex-wrap gap-2 text-xs">
                {specCategories.map((c) => (
                  <span key={c} className="rounded-full bg-gray-2 px-3 py-1 text-light">{c}</span>
                ))}
                {(spec.serves_all_cities || cityName) && (
                  <span className="rounded-full bg-gray-2 px-3 py-1 text-light">
                    {spec.serves_all_cities ? t("allCities") : cityName}
                  </span>
                )}
              </div>
            </div>
          )}
        </div>

        {profile.video_url && (
          <div className="w-full md:w-80 md:shrink-0">
            <div className="aspect-video overflow-hidden rounded-xl bg-gray-2">
              <iframe
                src={profile.video_url.replace("watch?v=", "embed/")}
                className="size-full"
                allowFullScreen
                title={t("videoIframeTitle")}
              />
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
