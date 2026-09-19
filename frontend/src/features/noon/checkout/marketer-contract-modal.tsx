"use client";
import { useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import { Spinner } from "@/src/components/ui/spinner";
import useLocale from "@/src/hooks/use-locale";
import { IMarketerContract } from "./types/marketer-contract.type";
import { getMarketerContractPdfBlob } from "./api/get";

type Props = {
  open: boolean;
  contract: IMarketerContract;
  onAccept: () => void;
  onClose: () => void;
  isSubmitting?: boolean;
};

const FOCUSABLE =
  'a[href],button:not([disabled]),input:not([disabled]),iframe,[tabindex]:not([tabindex="-1"])';

export default function MarketerContractModal(props: Props) {
  // Remount per version so scroll/read state resets between marketers.
  return props.open ? (
    <ModalBody key={props.contract.version_id} {...props} />
  ) : null;
}

function ModalBody({ contract, onAccept, onClose, isSubmitting = false }: Props) {
  const t = useTranslations("checkout");
  const locale = useLocale();
  const isPdf = contract.content_type === "pdf";

  const dialogRef = useRef<HTMLDivElement>(null);
  const endRef = useRef<HTMLDivElement>(null);
  const scrollRef = useRef<HTMLDivElement>(null);
  const [scrolledToEnd, setScrolledToEnd] = useState(false);
  const [confirmed, setConfirmed] = useState(false);
  const [pdfUrl, setPdfUrl] = useState<string | null>(null);
  const [pdfFailed, setPdfFailed] = useState(false);

  const title =
    (locale === "ar" ? contract.title_ar : contract.title_en) ||
    contract.title_en ||
    contract.title_ar ||
    t("marketerContractTitle");

  // Scroll-to-end gate for text contracts.
  useEffect(() => {
    if (isPdf) return;
    const root = scrollRef.current;
    const end = endRef.current;
    if (!root || !end || typeof IntersectionObserver === "undefined") {
      setScrolledToEnd(true);
      return;
    }
    const obs = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) setScrolledToEnd(true);
      },
      { root, threshold: 0.99 },
    );
    obs.observe(end);
    return () => obs.disconnect();
  }, [isPdf]);

  // Authenticated PDF via blob + object URL.
  useEffect(() => {
    if (!isPdf || !contract.file_url) return;
    let url: string | null = null;
    let cancelled = false;
    getMarketerContractPdfBlob(contract.file_url)
      .then((blob) => {
        if (cancelled) return;
        url = URL.createObjectURL(blob);
        setPdfUrl(url);
      })
      .catch(() => !cancelled && setPdfFailed(true));
    return () => {
      cancelled = true;
      if (url) URL.revokeObjectURL(url);
    };
  }, [isPdf, contract.file_url]);

  // Esc closes, focus trap, initial focus.
  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null;
    dialogRef.current?.focus();
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape" && !isSubmitting) {
        onClose();
        return;
      }
      if (e.key !== "Tab" || !dialogRef.current) return;
      const items = Array.from(
        dialogRef.current.querySelectorAll<HTMLElement>(FOCUSABLE),
      );
      if (items.length === 0) return;
      const first = items[0];
      const last = items[items.length - 1];
      const active = document.activeElement;
      if (e.shiftKey && (active === first || active === dialogRef.current)) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
      }
    };
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("keydown", onKey);
      previous?.focus?.();
    };
  }, [onClose, isSubmitting]);

  const canAccept = isPdf ? confirmed : scrolledToEnd;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      onClick={onClose}
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="marketer-contract-title"
        tabIndex={-1}
        className="bg-white rounded-lg w-full max-w-lg p-4 md:p-6 flex flex-col gap-4 max-h-[85vh] outline-none"
        onClick={(e) => e.stopPropagation()}
      >
        <h3
          id="marketer-contract-title"
          className="text-sm md:text-base font-semibold"
        >
          {title}
        </h3>

        <p className="text-xs md:text-sm text-gray">
          {t("marketerContractRequiredNotice")}
        </p>

        <div
          ref={scrollRef}
          className="flex-1 overflow-y-auto border border-border-color rounded-md p-3"
          tabIndex={0}
        >
          {!isPdf ? (
            <>
              <p className="text-xs md:text-sm whitespace-pre-wrap">
                {contract.text_content}
              </p>
              <div ref={endRef} aria-hidden="true" className="h-px" />
            </>
          ) : (
            <div className="flex flex-col gap-2">
              {pdfUrl ? (
                <iframe
                  src={pdfUrl}
                  title={title}
                  className="w-full h-[50vh] border-0"
                />
              ) : pdfFailed ? (
                <p className="text-xs text-red-500">
                  {t("marketerContractLoadFailed")}
                </p>
              ) : (
                <Spinner />
              )}
              {pdfUrl && (
                <a
                  href={pdfUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-blue text-xs md:text-sm underline"
                >
                  {t("marketerContractDownload")}
                </a>
              )}
            </div>
          )}
        </div>

        {isPdf ? (
          <label className="flex items-center gap-2 text-xs md:text-sm">
            <input
              type="checkbox"
              checked={confirmed}
              onChange={(e) => setConfirmed(e.target.checked)}
            />
            {t("marketerContractReadConfirm")}
          </label>
        ) : (
          !scrolledToEnd && (
            <p className="text-xs text-gray">{t("marketerContractScrollHint")}</p>
          )
        )}

        <div className="flex gap-2 justify-end">
          <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
            {t("marketerContractCancel")}
          </Button>
          <Button onClick={onAccept} disabled={isSubmitting || !canAccept}>
            {isSubmitting && <Spinner />}
            {t("marketerContractAccept")}
          </Button>
        </div>
      </div>
    </div>
  );
}
