import { Skeleton } from "@/src/components/ui/skeleton";
import React from "react";

export default function HomeSkeleton() {
  return (
    <div className="flex gap-4 flex-wrap container">
      <Skeleton className="w-full h-80" />
      {Array.from({ length: 8 }).map((e, i) => (
        <Skeleton key={i} className="w-[calc((100%-(16px*7))/8)] h-32" />
      ))}
      {Array.from({ length: 3 }).map((e, i) => (
        <Skeleton key={i} className="w-[calc((100%-(16px*2))/3)] h-112" />
      ))}
      {Array.from({ length: 6 }).map((e, i) => (
        <Skeleton key={i} className="w-[calc((100%-(16px*5))/6)] h-112" />
      ))}
      <Skeleton className="w-full h-80" />
    </div>
  );
}
