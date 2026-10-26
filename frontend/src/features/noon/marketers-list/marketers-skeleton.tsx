import { Skeleton } from "@/src/components/ui/skeleton";
import React from "react";

export default function MarketersSkeleton() {
  return (
    <div className="container">
      <Skeleton className="h-100" />
      <div className="flex gap-2">
        <Skeleton className="h-40 w-1/6" />
        <Skeleton className="h-40 w-1/6" />
        <Skeleton className="h-40 w-1/6" />
        <Skeleton className="h-40 w-1/6" />
        <Skeleton className="h-40 w-1/6" />
        <Skeleton className="h-40 w-1/6" />
      </div>
    </div>
  );
}
