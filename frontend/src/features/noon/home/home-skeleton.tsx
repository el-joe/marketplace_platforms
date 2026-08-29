import { Skeleton } from "@/src/components/ui/skeleton";
import React from "react";

export default function HomeSkeleton() {
  return (
    <div className="flex gap-x-4 gap-y-8 flex-wrap container">
      <Skeleton className="w-full h-78" />
      {Array.from({ length: 12 }).map((e, i) => (
        <Skeleton key={i} className="w-[calc((100%-(16px*11))/12)] h-48" />
      ))}
      {Array.from({ length: 3 }).map((e, i) => (
        <Skeleton key={i} className="w-[calc((100%-(16px*2))/3)] h-154" />
      ))}
      {Array.from({ length: 6 }).map((e, i) => (
        <Skeleton key={i} className="w-[calc((100%-(16px*5))/6)] h-112" />
      ))}
      <Skeleton className="w-full h-80" />
    </div>
  );
}
