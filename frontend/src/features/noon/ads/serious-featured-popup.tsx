"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import useLocale from "@/src/hooks/use-locale";
import { X } from "lucide-react";
import { Dialog } from "@base-ui/react/dialog";
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

  const isAr = locale === "ar";
  const title = (isAr ? popup.title_ar || popup.title_en : popup.title_en) ?? "";
  const body = (isAr ? popup.body_ar || popup.body_en : popup.body_en) ?? "";
  const ctaLabel =
    (isAr ? popup.cta_label_ar || popup.cta_label_en : popup.cta_label_en) ||
    t("viewProduct");
  const desktopImage = isAr
    ? popup.image_url_ar || popup.image_url
    : popup.image_url;
  const mobileImage =
    (isAr
      ? popup.image_url_mobile_ar || popup.image_url_mobile
      : popup.image_url_mobile) || desktopImage;
  // cta_url is built server-side from the booked destination (exact listing,
  // store, brand, category...); product_slug is only a fallback.
  const ctaHref =
    popup.cta_url ?? (popup.product_slug ? `/products/${popup.product_slug}` : null);

  return (
    <Dialog.Root open={open} onOpenChange={setOpen}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm transition-opacity duration-200 data-starting-style:opacity-0 data-ending-style:opacity-0" />
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none">
          <Dialog.Popup
            initialFocus={false}
            className="pointer-events-auto relative flex w-full max-w-md max-h-[90vh] flex-col overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 transition duration-200 data-starting-style:scale-95 data-starting-style:opacity-0 data-ending-style:scale-95 data-ending-style:opacity-0"
          >
            <Dialog.Close
              className="absolute top-3 end-3 z-10 flex size-9 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-md backdrop-blur transition hover:bg-white hover:text-gray-900 cursor-pointer"
              aria-label={t("close")}
            >
              <X className="size-5" />
            </Dialog.Close>

            {desktopImage && (
              <picture className="block bg-gray-100">
                {mobileImage && (
                  <source media="(max-width: 639px)" srcSet={mobileImage} />
                )}
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={desktopImage}
                  alt={title}
                  className="w-full h-auto max-h-[55vh] object-contain"
                />
              </picture>
            )}

            <div className="flex flex-col gap-2 overflow-y-auto p-6 text-center">
              {title && (
                <Dialog.Title className="text-xl font-extrabold leading-snug text-gray-900">
                  {title}
                </Dialog.Title>
              )}
              {body && (
                <Dialog.Description className="text-sm leading-relaxed text-gray-600">
                  {body}
                </Dialog.Description>
              )}

              {ctaHref && (
                <Button
                  render={<Link href={ctaHref} onClick={() => setOpen(false)} />}
                  className="mt-4 h-12 w-full justify-center rounded-xl bg-blue-3 font-bold uppercase text-white shadow-md transition hover:bg-blue-3/90"
                >
                  {ctaLabel}
                </Button>
              )}
            </div>
          </Dialog.Popup>
        </div>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
