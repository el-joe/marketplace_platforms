"use client";

import { useTranslations } from "next-intl";
import { UploadCloud, CheckCircle2 } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { useBankTransferProof } from "./helpers/use-bank-transfer-proof";

interface Props {
  orderNumber: string;
  details: Record<string, unknown>;
  alreadyUploaded?: boolean;
}

const FIELD_KEYS: { key: string; labelKey: string }[] = [
  { key: "bank_name", labelKey: "bankName" },
  { key: "account_name", labelKey: "accountName" },
  { key: "account_number", labelKey: "accountNumber" },
  { key: "iban", labelKey: "iban" },
  { key: "swift", labelKey: "swift" },
  { key: "reference", labelKey: "reference" },
];

export default function BankTransferCard({
  orderNumber,
  details,
  alreadyUploaded = false,
}: Props) {
  const t = useTranslations("checkoutSuccess");
  const { file, setFile, note, setNote, submitProof, isUploading, isUploaded } =
    useBankTransferProof(orderNumber, alreadyUploaded);

  return (
    <div className="bg-white rounded-2xl p-6 border border-border shadow-xs">
      <h3 className="text-lg font-bold text-[#404553] mb-1">
        {t("bankTransferTitle")}
      </h3>
      <p className="text-sm text-gray-500 mb-4">
        {t("bankTransferInstructions")}
      </p>

      <dl className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6 bg-gray-50 rounded-xl p-4 border border-gray-200/80">
        {FIELD_KEYS.filter(({ key }) => details[key]).map(({ key, labelKey }) => (
          <div key={key}>
            <dt className="text-xs text-gray-400 uppercase font-medium mb-0.5">
              {t(labelKey)}
            </dt>
            <dd className="font-mono text-sm text-gray-900 break-all">
              {String(details[key])}
            </dd>
          </div>
        ))}
      </dl>

      {isUploaded ? (
        <div className="flex items-center gap-2 text-emerald-600 text-sm font-medium">
          <CheckCircle2 className="size-5" />
          {t("proofUploaded")}
        </div>
      ) : (
        <div>
          <p className="text-sm font-semibold text-gray-800 mb-1">
            {t("uploadProof")}
          </p>
          <p className="text-xs text-gray-500 mb-3">{t("uploadProofDesc")}</p>
          <textarea
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder={t("notePlaceholder")}
            rows={2}
            className="w-full mb-3 rounded-lg border border-gray-300 p-2 text-sm resize-none"
          />
          <div className="flex flex-wrap items-center gap-3">
            <label className="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 cursor-pointer hover:bg-gray-50">
              <UploadCloud className="size-4" />
              {file ? file.name : t("chooseFile")}
              <input
                type="file"
                accept=".jpg,.jpeg,.png,.pdf"
                className="hidden"
                onChange={(e) => setFile(e.target.files?.[0] ?? null)}
              />
            </label>
            <Button
              type="button"
              disabled={!file || isUploading}
              onClick={submitProof}
              className="bg-[#3866df] hover:bg-[#2d52b5] text-white"
            >
              {isUploading ? t("uploadingProof") : t("submitProof")}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
