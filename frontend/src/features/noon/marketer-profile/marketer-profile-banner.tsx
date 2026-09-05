"use client";
import React, { useState } from "react";
import Image from "next/image";
import { Share2Icon, QrCodeIcon } from "lucide-react";

interface Props {
  bannerUrl: string | null;
  marketerName: string;
  marketerType: "influencer" | "affiliate";
  profileUrl: string;
  qrCodeUrl: string | null;
}

export default function MarketerProfileBanner({
  bannerUrl,
  marketerName,
  marketerType,
  profileUrl,
  qrCodeUrl,
}: Props) {
  const [copied, setCopied] = useState(false);
  const [showQr, setShowQr] = useState(false);

  const copyLink = () => {
    navigator.clipboard.writeText(profileUrl).catch(() => {});
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <>
      <div className="relative w-full h-56 md:h-72 lg:h-80 bg-gradient-to-br from-gray-800 to-gray-900 overflow-hidden">
        {bannerUrl ? (
          <Image src={bannerUrl} alt={marketerName} fill className="object-cover" priority />
        ) : (
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="w-24 h-24 rounded-full bg-yellow-400 flex items-center justify-center text-4xl font-black text-gray-900">
              {marketerName.charAt(0)}
            </div>
          </div>
        )}

        <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />

        <div className="absolute bottom-4 start-6 end-6 flex items-end justify-between">
          <div>
            <h1 className="text-white text-2xl md:text-3xl font-black drop-shadow-lg">
              {marketerName}
            </h1>
            <span
              className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold mt-1 ${
                marketerType === "influencer"
                  ? "bg-purple-500 text-white"
                  : "bg-blue-500 text-white"
              }`}
            >
              {marketerType === "influencer" ? "🎬 مؤثر" : "🔗 أفيليت"}
            </span>
          </div>

          <div className="flex gap-2">
            <button
              onClick={copyLink}
              className="flex items-center gap-1 px-3 py-1.5 bg-white/20 backdrop-blur-sm text-white text-xs font-semibold rounded-lg hover:bg-white/30 transition"
            >
              <Share2Icon className="w-3.5 h-3.5" />
              {copied ? "تم النسخ!" : "مشاركة"}
            </button>
            {qrCodeUrl && (
              <button
                onClick={() => setShowQr(true)}
                className="flex items-center gap-1 px-3 py-1.5 bg-white/20 backdrop-blur-sm text-white text-xs font-semibold rounded-lg hover:bg-white/30 transition"
              >
                <QrCodeIcon className="w-3.5 h-3.5" />
                QR
              </button>
            )}
          </div>
        </div>
      </div>

      {showQr && qrCodeUrl && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
          onClick={() => setShowQr(false)}
        >
          <div
            className="bg-white rounded-2xl p-6 text-center shadow-xl max-w-xs w-full mx-4"
            onClick={(e) => e.stopPropagation()}
          >
            <h3 className="font-bold text-gray-900 mb-3 text-lg">كود QR للبروفايل</h3>
            <Image
              src={qrCodeUrl}
              alt="QR Code"
              width={200}
              height={200}
              className="mx-auto rounded-xl"
            />
            <p className="text-xs text-gray-400 mt-3">
              امسح الكود للوصول لصفحة {marketerName}
            </p>
            <div className="flex gap-2 mt-4">
              <a
                href={qrCodeUrl}
                download={`marketer-${marketerName}-qr.png`}
                className="flex-1 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500"
              >
                تنزيل
              </a>
              <button
                onClick={() => setShowQr(false)}
                className="flex-1 py-2 bg-gray-100 text-gray-700 font-semibold rounded-lg text-sm hover:bg-gray-200"
              >
                إغلاق
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
