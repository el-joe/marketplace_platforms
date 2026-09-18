"use client";

import { PrinterIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";

type Props = {
  label: string;
};

/** "use client" leaf — the only interactive piece of the invoice view (triggers the browser print/save-as-PDF dialog). */
export default function PrintButton({ label }: Props) {
  return (
    <Button
      type="button"
      variant="outline"
      onClick={() => window.print()}
      className="gap-2"
    >
      <PrinterIcon className="size-4" />
      {label}
    </Button>
  );
}
