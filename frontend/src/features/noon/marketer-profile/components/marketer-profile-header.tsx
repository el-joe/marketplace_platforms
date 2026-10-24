import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { ShoppingBag, TrendingUp } from "lucide-react";
import ProfileActions from "./profile-actions";
import type { MarketerProfileInfo, MarketerProfileMarketer } from "../helpers/types";

interface Props {
  marketer: MarketerProfileMarketer;
  profile: MarketerProfileInfo;
}

/** H1 (marketer name) + avatar, type badge, stats and share/QR actions. */
export default async function MarketerProfileHeader({ marketer, profile }: Props) {
  const t = await getTranslations("marketerProfile");
  const isInfluencer = marketer.marketer_type === "influencer";
  const typeLabel =
    marketer.marketer_type === "broker"
      ? t("brokerBadge")
      : isInfluencer
        ? t("influencerBadge")
        : t("affiliateBadge");

  return (
    <div className="flex flex-wrap items-center gap-4">
      <div className="relative size-16 shrink-0 overflow-hidden rounded-full bg-gradient-to-br from-amber-300 via-main to-yellow-200 p-0.5 md:size-20">
        <div className="relative size-full overflow-hidden rounded-full bg-white">
          {profile.avatar_url ? (
            <Image src={profile.avatar_url} alt={marketer.name} fill sizes="80px" className="object-cover" />
          ) : (
            <div className="flex size-full items-center justify-center bg-gradient-to-br from-amber-400 to-amber-600 text-2xl font-black text-white">
              {marketer.name.charAt(0)}
            </div>
          )}
        </div>
      </div>

      <div className="min-w-0 flex-1">
        <h1 className="line-clamp-2 break-words text-2xl font-black text-primary md:text-3xl">{marketer.name}</h1>
        <div className="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-semibold text-gray">
          <span className="rounded-full bg-gray-2 px-3 py-1 text-primary">{typeLabel}</span>
          <span className="inline-flex items-center gap-1">
            <ShoppingBag className="size-3.5" />
            {marketer.total_campaigns} {t("campaigns")}
          </span>
          <span className="inline-flex items-center gap-1 text-green">
            <TrendingUp className="size-3.5" />
            {marketer.total_conversions} {t("sales")}
          </span>
        </div>
      </div>

      <ProfileActions
        profileUrl={profile.profile_url}
        qrCodeUrl={profile.qr_code_url}
        marketerName={marketer.name}
        labels={{
          copied: t("copied"),
          share: t("share"),
          qrTitle: t("qrTitle"),
          qrScanHint: t("qrScanHint", { name: marketer.name }),
          qrCodeAlt: t("qrCodeAlt"),
          download: t("download"),
          close: t("close"),
        }}
      />
    </div>
  );
}
