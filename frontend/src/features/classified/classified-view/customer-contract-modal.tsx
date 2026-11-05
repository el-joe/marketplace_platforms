"use client";

import { useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import { Spinner } from "@/src/components/ui/spinner";
import ContractFileViewer from "@/src/components/shared/contract-file-viewer";
import useLocale from "@/src/hooks/use-locale";
import type { CustomerContractPayload } from "@/src/services/customer-contracts";

type Props = {
  open: boolean;
  contract: CustomerContractPayload | null;
  isSubmitting?: boolean;
  /** Server message of the last failed attempt (contract changed, validation, network...). */
  error?: string | null;
  defaultName?: string;
  onAccept: (signerName: string) => void;
  onClose: () => void;
};

const FOCUSABLE =
  'a[href],button:not([disabled]),input:not([disabled]),iframe,[tabindex]:not([tabindex="-1"])';
const MIN_NAME_LENGTH = 2;

export default function CustomerContractModal(props: Props) {
  // Remount per version so the read / typed state resets when the contract changes while it is open.
  return props.open && props.contract ? (
    <ModalBody key={props.contract.version} {...props} contract={props.contract} />
  ) : null;
}

function ModalBody({
  contract,
  isSubmitting = false,
  error,
  defaultName = "",
  onAccept,
  onClose,
}: Props & { contract: CustomerContractPayload }) {
  const t = useTranslations("customerContract");
  const locale = useLocale();
  const isFile = contract.content_mode === "file";
  const file = contract.files[locale] ?? contract.files.ar ?? contract.files.en;

  const dialogRef = useRef<HTMLDivElement>(null);
  const endRef = useRef<HTMLDivElement>(null);
  const scrollRef = useRef<HTMLDivElement>(null);
  const [scrolledToEnd, setScrolledToEnd] = useState(isFile);
  const [accepted, setAccepted] = useState(false);
  const [name, setName] = useState(defaultName);

  // Text contracts must be scrolled to the end before they can be accepted.
  useEffect(() => {
    if (isFile) return;
    const root = scrollRef.current;
    const end = endRef.current;
    if (!root || !end || typeof IntersectionObserver === "undefined") {
      setScrolledToEnd(true);
      return;
    }
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) setScrolledToEnd(true);
      },
      { root, threshold: 0.99 },
    );
    observer.observe(end);
    return () => observer.disconnect();
  }, [isFile]);

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

  const meta = [
    t("version", { version: contract.version }),
    contract.listing.number,
    contract.country?.name,
  ]
    .filter(Boolean)
    .join(" · ");
  const trimmedName = name.trim();
  const canAccept =
    !isSubmitting &&
    scrolledToEnd &&
    accepted &&
    trimmedName.length >= MIN_NAME_LENGTH;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-3 sm:p-4"
      onClick={() => !isSubmitting && onClose()}
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="customer-contract-title"
        tabIndex={-1}
        className="bg-white rounded-lg w-full max-w-lg p-4 md:p-6 flex flex-col gap-3 md:gap-4 max-h-[92vh] outline-none"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex flex-col gap-1">
          <h3
            id="customer-contract-title"
            className="text-sm md:text-base font-semibold"
          >
            {contract.name}
          </h3>
          <p className="text-xs text-gray">{meta}</p>
        </div>

        <p className="text-xs md:text-sm text-gray">{t("requiredNotice")}</p>

        <div
          ref={scrollRef}
          className="flex-1 min-h-32 overflow-y-auto border border-border-color rounded-md p-3"
          tabIndex={0}
        >
          {isFile ? (
            file ? (
              <ContractFileViewer
                url={file.url}
                name={file.name}
                inline={file.inline}
                title={contract.name}
              />
            ) : (
              <p className="text-xs text-red-500">{t("loadFailed")}</p>
            )
          ) : (
            <>
              <div
                className="text-xs md:text-sm [&_p]:mb-2 [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:ps-5 [&_ol]:ps-5"
                dir={locale === "ar" ? "rtl" : "ltr"}
                dangerouslySetInnerHTML={{ __html: contract.content }}
              />
              <div ref={endRef} aria-hidden="true" className="h-px" />
            </>
          )}
        </div>

        {!isFile && !scrolledToEnd && (
          <p className="text-xs text-gray">{t("scrollHint")}</p>
        )}

        <div className="flex flex-col gap-1">
          <label htmlFor="customer-contract-name" className="text-xs font-medium">
            {t("nameLabel")}
          </label>
          <input
            id="customer-contract-name"
            type="text"
            value={name}
            maxLength={150}
            autoComplete="name"
            disabled={isSubmitting}
            onChange={(e) => setName(e.target.value)}
            placeholder={t("namePlaceholder")}
            className="w-full text-sm px-3 py-2 rounded-md border border-border-color focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <label className="flex items-start gap-2 text-xs md:text-sm">
          <input
            type="checkbox"
            className="mt-0.5"
            checked={accepted}
            disabled={isSubmitting}
            onChange={(e) => setAccepted(e.target.checked)}
          />
          {t("acceptLabel")}
        </label>

        <p className="text-[11px] text-gray">{t("recordedNotice")}</p>

        {error && (
          <p role="alert" className="text-xs text-red-500">
            {error}
          </p>
        )}

        <div className="flex gap-2 justify-end">
          <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
            {t("cancel")}
          </Button>
          <Button onClick={() => onAccept(trimmedName)} disabled={!canAccept}>
            {isSubmitting && <Spinner />}
            {t("accept")}
          </Button>
        </div>
      </div>
    </div>
  );
}
