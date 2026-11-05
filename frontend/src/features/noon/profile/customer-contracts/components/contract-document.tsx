"use client";

import ContractFileViewer from "@/src/components/shared/contract-file-viewer";
import type { MyContractDetail } from "@/src/services/customer-contracts";

type Props = {
  contract: MyContractDetail;
};

/** The exact text (or document) the customer accepted. */
export default function ContractDocument({ contract }: Props) {
  if (contract.content_mode === "file" && contract.file_url) {
    return (
      <ContractFileViewer
        url={contract.file_url}
        name={contract.file_name ?? contract.name ?? "contract"}
        inline={(contract.file_name ?? "").toLowerCase().endsWith(".pdf")}
        title={contract.name ?? ""}
      />
    );
  }

  return (
    <div
      className="text-sm [&_p]:mb-2 [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:ps-5 [&_ol]:ps-5"
      dir={contract.language === "ar" ? "rtl" : "ltr"}
      dangerouslySetInnerHTML={{ __html: contract.content }}
    />
  );
}
