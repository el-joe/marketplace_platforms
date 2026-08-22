import { Skeleton } from "@/src/components/ui/skeleton";
import React from "react";

export default function HomeSkeleton() {
  return (
    <div className="flex gap-4 flex-wrap container">
      {Array.from({ length: 40 }).map((e, i) => (
        <Skeleton key={i} className="w-[calc((100%-48px)/4)] h-32" />
      ))}
    </div>
  );
}
