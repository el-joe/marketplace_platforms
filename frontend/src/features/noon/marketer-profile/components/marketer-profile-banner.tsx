import Image from "next/image";
import { getTranslations } from "next-intl/server";
import ProfileActions from "./profile-actions";

interface Props {
  bannerUrl: string | null;
  avatarUrl: string | null;
  marketerName: string;
  marketerType: "influencer" | "affiliate";
  profileUrl: string;
  qrCodeUrl: string | null;
}

export default async function MarketerProfileBanner({
  bannerUrl,
  avatarUrl,
  marketerName,
  marketerType,
  profileUrl,
  qrCodeUrl,
}: Props) {
  const t = await getTranslations("marketerProfile");
  const isInfluencer = marketerType === "influencer";

  return (
    <div className="relative h-64 w-full overflow-hidden bg-neutral-950 md:h-80 lg:h-96">
      {bannerUrl ? (
        <Image src={bannerUrl} alt={marketerName} fill className="object-cover opacity-60" priority />
      ) : (
        <>
          <div className="pointer-events-none absolute -top-20 left-1/4 h-72 w-72 rounded-full bg-main/25 blur-[100px]" />
          <div className="pointer-events-none absolute top-0 right-0 h-64 w-64 rounded-full bg-fuchsia-500/20 blur-[100px]" />
          <div className="pointer-events-none absolute bottom-0 left-0 h-56 w-56 rounded-full bg-sky-500/20 blur-[100px]" />
          <div
            className="pointer-events-none absolute inset-0 opacity-[0.07]"
            style={{
              backgroundImage:
                "linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px)",
              backgroundSize: "42px 42px",
            }}
          />
        </>
      )}

      <div className="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent" />

      <div className="absolute top-4 flex w-full justify-end px-6">
        <ProfileActions
          profileUrl={profileUrl}
          qrCodeUrl={qrCodeUrl}
          marketerName={marketerName}
          labels={{
            copied: t("copied"),
            share: t("share"),
            qrTitle: t("qrTitle"),
            qrScanHint: t("qrScanHint", { name: marketerName }),
            download: t("download"),
            close: t("close"),
          }}
        />
      </div>

      <div className="absolute bottom-6 start-6 end-6 flex items-end gap-4">
        <div className="relative h-20 w-20 shrink-0 rounded-full bg-gradient-to-br from-amber-300 via-main to-yellow-200 p-0.75 shadow-xl md:h-24 md:w-24">
          <div className="h-full w-full rounded-full bg-white p-0.75">
            <div className="relative h-full w-full overflow-hidden rounded-full">
              {avatarUrl ? (
                <Image src={avatarUrl} alt={marketerName} fill className="object-cover" />
              ) : (
                <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-amber-400 to-amber-600 text-3xl font-black text-white">
                  {marketerName.charAt(0)}
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="min-w-0">
          <h1 className="truncate text-2xl font-black text-white drop-shadow-lg md:text-4xl">
            {marketerName}
          </h1>
          <span
            className={`mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold backdrop-blur-md ${
              isInfluencer ? "bg-purple-950/60 text-purple-100" : "bg-blue-950/60 text-blue-100"
            }`}
          >
            {isInfluencer ? t("influencerBadge") : t("affiliateBadge")}
          </span>
        </div>
      </div>
    </div>
  );
}
