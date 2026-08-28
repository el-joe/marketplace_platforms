"use client";

import { useState } from "react";
import { Copy, Check, Share2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";

interface Props {
  referralLink: string;
}

export default function CopyReferralLink({ referralLink }: Props) {
  const t = useTranslations("profile");
  const [copied, setCopied] = useState(false);

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(referralLink);
    } catch {
      const el = document.createElement("textarea");
      el.value = referralLink;
      document.body.appendChild(el);
      el.select();
      document.execCommand("copy");
      document.body.removeChild(el);
    }
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleShare = async () => {
    if (navigator.share) {
      try {
        await navigator.share({ title: t("qrShareTitle"), url: referralLink });
      } catch {
        // User cancelled share — ignore
      }
    } else {
      window.open(referralLink, "_blank", "noopener,noreferrer");
    }
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
        <span className="flex-1 text-sm text-gray-600 truncate" dir="ltr">
          {referralLink}
        </span>
        <button
          type="button"
          onClick={handleCopy}
          className="shrink-0 text-gray-500 hover:text-primary transition-colors"
          title={t("qrCopyLink")}
          aria-label={t("qrCopyLink")}
        >
          {copied ? (
            <Check className="size-4 text-green-600" />
          ) : (
            <Copy className="size-4" />
          )}
        </button>
      </div>

      <div className="flex gap-2">
        <Button variant="outline" size="sm" className="flex-1 gap-2" onClick={handleCopy}>
          {copied ? (
            <Check className="size-4 text-green-600" />
          ) : (
            <Copy className="size-4" />
          )}
          {copied ? t("qrCopied") : t("qrCopyLink")}
        </Button>

        <Button variant="outline" size="sm" className="flex-1 gap-2" onClick={handleShare}>
          <Share2 className="size-4" />
          {t("qrShare")}
        </Button>
      </div>
    </div>
  );
}
