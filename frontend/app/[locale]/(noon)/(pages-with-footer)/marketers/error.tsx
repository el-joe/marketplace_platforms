"use client";

import MarketersError from "@/src/features/noon/marketers-list/components/marketers-error";

export default function Error({ reset }: { error: Error; reset: () => void }) {
  return <MarketersError reset={reset} />;
}
