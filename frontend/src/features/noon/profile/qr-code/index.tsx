import { getTranslations } from "next-intl/server";
import Image from "next/image";
import Card from "@/src/components/shared/Card";
import { getQrCode } from "./api/qr-code.actions";

export default async function QrCode() {
  const t = await getTranslations("profile");

  let qrCode = null;
  try {
    qrCode = await getQrCode();
  } catch {
    // QR code not yet generated or fetch failed — show empty state
  }

  return (
    <>
      <h1 className="text-[28px] font-bold  text-light">{t("qrCodeTitle")}</h1>
      <p className="mt-1 text-gray">{t("qrCodeSubtitle")}</p>

      <Card className="mt-4 flex flex-col items-center gap-4 p-6">
        {qrCode?.qr_url ? (
          <div className="relative size-39.5 shrink-0">
            <Image
              src={qrCode.qr_url}
              alt={t("qrCodeTitle")}
              fill
              className="object-contain h-full w-full"
            />
          </div>
        ) : (
          <p className="text-gray text-sm py-8">{t("qrCodeUnavailable")}</p>
        )}
      </Card>
    </>
  );
}
