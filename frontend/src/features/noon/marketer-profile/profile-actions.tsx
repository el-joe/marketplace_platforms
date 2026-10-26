"use client";
import { useState } from "react";
import Image from "next/image";
import { getImageURL } from "@/src/helpers/get-image-url";
import { Share2, QrCode, Download, X } from "lucide-react";
import { useTranslations } from "next-intl";

interface Props {
  profileUrl: string;
  qrCodeUrl: string | null;
  marketerName: string;
}

export default function ProfileActions({
  profileUrl,
  qrCodeUrl,
  marketerName,
}: Props) {
  const [copied, setCopied] = useState(false);
  const [showQr, setShowQr] = useState(false);
  const t = useTranslations("marketerProfile");

  const copyLink = () => {
    navigator.clipboard.writeText(profileUrl).catch(() => {});
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <>
      <div className="flex gap-2">
        <button
          onClick={copyLink}
          className="flex items-center gap-1.5 rounded-full border border-border-color bg-white px-3.5 py-2 text-xs font-bold text-primary transition hover:bg-gray-2"
        >
          <Share2 className="h-3.5 w-3.5" />
          {copied ? t("copied") : t("share")}
        </button>
        {qrCodeUrl && (
          <button
            onClick={() => setShowQr(true)}
            className="flex items-center gap-1.5 rounded-full border border-border-color bg-white px-3.5 py-2 text-xs font-bold text-primary transition hover:bg-gray-2"
          >
            <QrCode className="h-3.5 w-3.5" />
            QR
          </button>
        )}
      </div>

      {showQr && qrCodeUrl && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
          onClick={() => setShowQr(false)}
        >
          <div
            className="relative mx-4 w-full max-w-xs rounded-2xl bg-white p-6 text-center shadow-xl"
            onClick={(e) => e.stopPropagation()}
          >
            <button
              onClick={() => setShowQr(false)}
              className="absolute top-3 ltr:right-3 rtl:left-3 rounded-full p-1 text-gray hover:bg-gray-2"
            >
              <X className="h-4 w-4" />
            </button>
            <h3 className="mb-3 text-lg font-bold text-primary">
              {t("qrTitle")}
            </h3>
            <Image
              src={getImageURL(qrCodeUrl)}
              alt={t("qrCodeAlt")}
              width={200}
              height={200}
              className="mx-auto rounded-xl"
            />
            <p className="mt-3 text-xs text-gray">
              {t("qrScanHint", { name: marketerName })}
            </p>
            <div className="mt-4 flex gap-2">
              <a
                href={qrCodeUrl}
                download={`marketer-${marketerName}-qr.png`}
                className="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-main py-2 text-sm font-bold text-primary hover:brightness-95"
              >
                <Download className="h-3.5 w-3.5" />
                {t("download")}
              </a>
              <button
                onClick={() => setShowQr(false)}
                className="flex-1 rounded-lg bg-gray-2 py-2 text-sm font-semibold text-light hover:bg-gray-4"
              >
                {t("close")}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
