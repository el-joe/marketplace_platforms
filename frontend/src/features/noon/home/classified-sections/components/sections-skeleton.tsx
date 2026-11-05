import { Skeleton } from "@/src/components/ui/skeleton";

export default function SectionsSkeleton() {
  return (
    <div className="container flex flex-col gap-8 py-6" aria-hidden>
      <div>
        <Skeleton className="mb-3 h-7 w-48" />
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="aspect-4/3 w-full rounded-xl" />
          ))}
        </div>
      </div>
    </div>
  );
}
