const PORTRAIT_COUNT = 6;

function PortraitSkeleton() {
  return (
    <div>
      <div className="aspect-[283/324] w-full rounded-xl bg-gray-2" />
      <div className="mx-auto mt-3 h-4 w-2/3 rounded bg-gray-2" />
    </div>
  );
}

export default function MarketersSkeleton() {
  return (
    <div className="container mx-auto animate-pulse px-4 py-6 sm:py-8" aria-busy="true">
      <div className="aspect-[16/9] w-full rounded-2xl bg-gray-2 md:aspect-[1700/450]" />
      <div className="mx-auto mt-8 h-11 w-56 rounded-full bg-gray-2" />
      {[0, 1].map((row) => (
        <div key={row} className="mt-10">
          <div className="mb-5 flex items-center justify-between">
            <div className="h-7 w-40 rounded bg-gray-2" />
            <div className="h-8 w-24 rounded-full bg-gray-2" />
          </div>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            {Array.from({ length: PORTRAIT_COUNT }, (_, i) => (
              <PortraitSkeleton key={i} />
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
