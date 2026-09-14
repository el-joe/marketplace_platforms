"use client";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import { Spinner } from "@/src/components/ui/spinner";
import { IMarketerContract } from "./types/marketer-contract.type";

type Props = {
  open: boolean;
  contract: IMarketerContract;
  onAccept: () => void;
  onClose: () => void;
  isSubmitting?: boolean;
};

export default function MarketerContractModal({
  open,
  contract,
  onAccept,
  onClose,
  isSubmitting = false,
}: Props) {
  const t = useTranslations("checkout");

  if (!open) return null;

  const title = contract.title_en || t("marketerContractTitle");

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      onClick={onClose}
    >
      <div
        className="bg-white rounded-lg w-full max-w-lg p-4 md:p-6 flex flex-col gap-4 max-h-[85vh]"
        onClick={(e) => e.stopPropagation()}
      >
        <h3 className="text-sm md:text-base font-semibold">{title}</h3>

        <p className="text-xs md:text-sm text-gray">
          {t("marketerContractRequiredNotice")}
        </p>

        <div className="flex-1 overflow-y-auto border border-border-color rounded-md p-3">
          {contract.content_type === "text" ? (
            <p className="text-xs md:text-sm whitespace-pre-wrap">
              {contract.text_content}
            </p>
          ) : (
            <a
              href={contract.file_url ?? undefined}
              target="_blank"
              rel="noopener noreferrer"
              className="text-blue text-xs md:text-sm underline"
            >
              {t("marketerContractDownload")}
            </a>
          )}
        </div>

        <div className="flex gap-2 justify-end">
          <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
            {t("marketerContractCancel")}
          </Button>
          <Button onClick={onAccept} disabled={isSubmitting}>
            {isSubmitting && <Spinner />}
            {t("marketerContractAccept")}
          </Button>
        </div>
      </div>
    </div>
  );
}
