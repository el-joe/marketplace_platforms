"use client";
import { UploadCloud } from "lucide-react";
import { useTranslations } from "next-intl";

type Props = {
  file: File | null;
  setFile: (file: File | null) => void;
  note: string;
  setNote: (note: string) => void;
};

export default function OfflinePaymentProofCard({
  file,
  setFile,
  note,
  setNote,
}: Props) {
  const t = useTranslations("checkout");

  return (
    <div className="p-3 bg-white rounded-2xl">
      <h3 className="text-base font-semibold mb-1">
        {t("offlinePaymentUploadTitle")}
      </h3>
      <p className="text-sm text-gray mb-3">{t("offlinePaymentUploadDesc")}</p>

      <label className="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 cursor-pointer hover:bg-gray-50 mb-3">
        <UploadCloud className="size-4" />
        {file ? file.name : t("chooseFile")}
        <input
          type="file"
          accept=".jpg,.jpeg,.png,.pdf"
          className="hidden"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
        />
      </label>

      <div>
        <label className="text-sm font-medium text-gray-700 mb-1 block">
          {t("note")}
        </label>
        <textarea
          value={note}
          onChange={(e) => setNote(e.target.value)}
          placeholder={t("notePlaceholder")}
          rows={2}
          className="w-full rounded-lg border border-gray-300 p-2 text-sm resize-none"
        />
      </div>
    </div>
  );
}
