"use client";

import { DownloadIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { Spinner } from "@/src/components/ui/spinner";
import { useContractDownload } from "../helpers/use-contract-download";

type Props = {
  downloadUrl: string;
  fileName: string;
  label: string;
  failedMessage: string;
  className?: string;
};

export default function ContractDownloadButton({ downloadUrl, fileName, label, failedMessage, className }: Props) {
  const { download, isDownloading } = useContractDownload(downloadUrl, fileName, failedMessage);

  return (
    <Button
      type="button"
      size="sm"
      variant="outline"
      onClick={download}
      disabled={isDownloading}
      className={className}
    >
      {isDownloading ? <Spinner /> : <DownloadIcon className="size-4" />}
      {label}
    </Button>
  );
}
