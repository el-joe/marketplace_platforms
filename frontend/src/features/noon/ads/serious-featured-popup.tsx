"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import useLocale from "@/src/hooks/use-locale";
import { X } from "lucide-react";
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetTitle,
} from "@/src/components/ui/sheet";
import { Button } from "@/src/components/ui/button";
import { getActivePopup } from "./api";
import type { AdPopup } from "./types";

const SEEN_KEY = "nawi_ads_popup_seen";

function hasSeenThisSession(): boolean {
  try {
    return sessionStorage.getItem(SEEN_KEY) === "1";
  } catch {
    return false;
  }
}

function markSeenThisSession(): void {
  try {
    sessionStorage.setItem(SEEN_KEY, "1");
  } catch {
    // ignore — sessionStorage can throw (private browsing, disabled storage, etc.)
  }
}

export default function SeriousFeaturedPopup() {
  const t = useTranslations("adsPopup");
  const locale = useLocale();
  const [popup, setPopup] = useState<AdPopup | null>(null);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (hasSeenThisSession()) return;

    let cancelled = false;

    getActivePopup()
      .then((data) => {
        if (cancelled || !data) return;
        setPopup(data);
        setOpen(true);
        markSeenThisSession();
      })
      .catch(() => {
        // silently ignore — a failed popup fetch should never break the storefront
      });

    return () => {
      cancelled = true;
    };
  }, []);

  if (!popup) return null;

  const title = (locale === "ar" ? popup.title_ar : popup.title_en) ?? "";
  const body = (locale === "ar" ? popup.body_ar : popup.body_en) ?? "";
  const ctaHref = popup.product_slug
    ? `/products/${popup.product_slug}`
    : popup.cta_url;

  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetContent
        side="right"
        className="w-full sm:max-w-md! p-0 flex flex-col overflow-hidden bg-white gap-0 rounded-2xl sm:m-auto sm:inset-0 sm:h-fit sm:max-h-[85vh] sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2"
        showCloseButton={false}
        initialFocus={false}
      >
        <div className="relative">
          <SheetClose
            render={
              <button
                type="button"
                className="absolute top-3 end-3 z-10 rounded-full bg-white/90 p-1.5 text-gray-600 hover:bg-white hover:text-gray-900 transition-colors cursor-pointer"
                aria-label={t("close")}
              />
            }
          >
            <X className="w-5 h-5" />
          </SheetClose>

          {popup.image_url && (
            <div className="relative w-full aspect-square bg-gray-100">
              <Image
                src={popup.image_url}
                alt={title}
                fill
                className="object-cover"
                sizes="(max-width: 640px) 100vw, 480px"
              />
            </div>
          )}

          <div className="p-5 flex flex-col gap-2">
            <SheetTitle className="text-lg font-bold text-gray-900">
              {title}
            </SheetTitle>
            {body && (
              <p className="text-sm text-gray-600 leading-relaxed">{body}</p>
            )}

            {ctaHref && (
              <Button
                render={<Link href={ctaHref} onClick={() => setOpen(false)} />}
                className="mt-3 w-full justify-center bg-blue-3 text-white hover:bg-blue-3/90 rounded-xl py-2.5 font-bold uppercase"
              >
                {t("viewProduct")}
              </Button>
            )}
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
