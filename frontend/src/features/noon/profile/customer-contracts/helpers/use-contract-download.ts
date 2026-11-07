"use client";

import { useState } from "react";
import toast from "react-hot-toast";
import { downloadMyContract } from "@/src/services/customer-contracts";

/** Downloads a copy of an accepted contract; `failedMessage` is shown when the download fails. */
export function useContractDownload(downloadUrl: string, fallbackName: string, failedMessage: string) {
  const [isDownloading, setIsDownloading] = useState(false);

  const download = async () => {
    if (isDownloading) return;
    setIsDownloading(true);
    try {
      await downloadMyContract(downloadUrl, fallbackName);
    } catch {
      toast.error(failedMessage);
    } finally {
      setIsDownloading(false);
    }
  };

  return { download, isDownloading };
}
