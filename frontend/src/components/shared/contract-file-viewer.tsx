"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Spinner } from "@/src/components/ui/spinner";
import { getContractFileBlob } from "@/src/services/customer-contracts";

type Props = {
  /** Authenticated API URL of the document. */
  url: string;
  name: string;
  /** True when the document is (or has a preview that is) a PDF that can be shown inline. */
  inline: boolean;
  title: string;
};

/**
 * Shows a contract document served by an auth-only API route: fetched with the bearer token, shown inline when a PDF
 * preview exists, and always offered as a download.
 */
export default function ContractFileViewer({ url, name, inline, title }: Props) {
  const t = useTranslations("customerContract");
  const [objectUrl, setObjectUrl] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let created: string | null = null;
    let cancelled = false;

    getContractFileBlob(url)
      .then((blob) => {
        if (cancelled) return;
        created = URL.createObjectURL(blob);
        setObjectUrl(created);
      })
      .catch(() => {
        if (!cancelled) setFailed(true);
      });

    return () => {
      cancelled = true;
      if (created) URL.revokeObjectURL(created);
    };
  }, [url]);

  if (failed) {
    return <p className="text-xs text-red-500">{t("loadFailed")}</p>;
  }

  if (!objectUrl) {
    return (
      <div className="flex justify-center py-6">
        <Spinner />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-2">
      {inline && (
        <iframe
          src={objectUrl}
          title={title}
          className="h-[50vh] w-full border-0"
        />
      )}
      <a
        href={objectUrl}
        download={name}
        className="text-blue text-xs md:text-sm underline"
      >
        {t("downloadDocument", { name })}
      </a>
    </div>
  );
}
