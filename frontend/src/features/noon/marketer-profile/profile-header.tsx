import Image from "next/image";
import { getTranslations } from "next-intl/server";
import getLocale from "@/src/helpers/getLocale";
import { getImageURL } from "@/src/helpers/get-image-url";
import ProfileActions from "./profile-actions";
import StartChatButton from "@/src/features/noon/chat/components/start-chat-button";
import { IMarketer } from "./helpers/types";

const FALLBACK_AVATAR = {
  male: "/images/male-avatar.webp",
  female: "/images/female-avatar.webp",
} as const;

export default async function ProfileHeader({ data }: { data: IMarketer }) {
  const [t, locale] = await Promise.all([
    getTranslations("marketerProfile"),
    getLocale(),
  ]);
  const { marketer, profile } = data;
  const isAr = locale === "ar";

  const specialty = isAr
    ? profile.specialty_ar || profile.specialty_en
    : profile.specialty_en || profile.specialty_ar;
  const bio = isAr
    ? profile.bio_ar || profile.bio_en
    : profile.bio_en || profile.bio_ar;
  const country = isAr ? marketer.country?.name_ar : marketer.country?.name_en;
  const badge =
    marketer.marketer_type === "influencer"
      ? t("influencerBadge")
      : marketer.marketer_type === "affiliate"
        ? t("affiliateBadge")
        : null;
  const avatar = profile.avatar_url
    ? getImageURL(profile.avatar_url)
    : FALLBACK_AVATAR[profile.gender === "male" ? "male" : "female"];

  return (
    <section className="mb-6">
      {/* cover */}
      <div className="relative h-48 w-full overflow-hidden bg-gray-2 sm:h-64 lg:h-80">
        {profile.banner_url ? (
          <Image
            src={getImageURL(profile.banner_url)}
            alt=""
            fill
            priority
            sizes="100vw"
            className="object-cover"
          />
        ) : (
          <div className="h-full w-full bg-linear-to-br from-gray-200 to-gray-300" />
        )}
        <div className="absolute inset-0 bg-linear-to-t from-black/40 via-transparent to-transparent" />
      </div>

      {/* identity card, overlapping the cover */}
      <div className="container">
        <div className="relative -mt-14 flex flex-col gap-4 rounded-2xl border border-border-color bg-white p-4 shadow-sm sm:-mt-16 sm:flex-row sm:items-end sm:gap-6 sm:p-6">
          <div className="-mt-16 h-28 w-28 shrink-0 overflow-hidden rounded-full border-4 border-white bg-gray-2 shadow-lg sm:-mt-20 sm:h-36 sm:w-36">
            <Image
              src={avatar}
              alt={marketer.name}
              width={288}
              height={288}
              priority
              className="h-full w-full object-cover"
            />
          </div>

          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="truncate text-xl font-bold sm:text-2xl">
                {marketer.name}
              </h1>
              {badge && (
                <span className="rounded-full bg-gray-2 px-2.5 py-1 text-xs font-bold">
                  {badge}
                </span>
              )}
            </div>
            {specialty && (
              <p className="mt-1 text-sm text-gray-500">{specialty}</p>
            )}
            {country && (
              <p className="mt-0.5 text-sm text-gray-500">
                {t("country")} {country}
              </p>
            )}
            {bio && (
              <p className="mt-2 line-clamp-2 max-w-2xl text-sm">{bio}</p>
            )}
          </div>

          <div className="flex shrink-0 flex-col gap-3 sm:items-end">
            <div className="flex gap-6">
              <div className="text-center">
                <p className="text-lg font-bold">{marketer.total_campaigns}</p>
                <p className="text-xs text-gray-500">{t("campaigns")}</p>
              </div>
              <div className="text-center">
                <p className="text-lg font-bold">
                  {marketer.total_conversions}
                </p>
                <p className="text-xs text-gray-500">{t("sales")}</p>
              </div>
            </div>
            <ProfileActions
              marketerName={marketer.name}
              profileUrl={profile.profile_url}
              qrCodeUrl={profile.qr_code_url}
            />
            <StartChatButton marketerSlug={profile.slug} />
          </div>
        </div>
      </div>
    </section>
  );
}
