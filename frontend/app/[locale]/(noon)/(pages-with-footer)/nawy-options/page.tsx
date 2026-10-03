import NawyOptions from "@/src/features/noon/nawy-options";
import NawyOptionsSkeleton from "@/src/features/noon/nawy-options/skeleton";
import React, { Suspense } from "react";

export default function page() {
  return (
    <Suspense fallback={<NawyOptionsSkeleton />}>
      <NawyOptions />
    </Suspense>
  );
}
