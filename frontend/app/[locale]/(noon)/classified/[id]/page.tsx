import ClassifiedBrowse from "@/src/features/open-sooq/browse";
import ClassifiedFilterSidebar from "@/src/features/open-sooq/browse/components/classified-filter-sidebar";

type Props = {
  params: Promise<{ id: string }>;
  searchParams: Promise<Record<string, string>>;
};

export default async function ClassifiedBrowsePage({
  params,
  searchParams,
}: Props) {
  const { id } = await params;
  const sp = await searchParams;

  return (
    <main className="container flex flex-col gap-4 py-4 lg:flex-row lg:gap-6">
      <aside className="w-[250px] h-[calc(100vh-100px)] sticky top-[100px] overflow-y-auto scrollbar-hide hidden lg:block">
        <ClassifiedFilterSidebar />
      </aside>
      <div className="min-w-0 flex-1">
        <ClassifiedBrowse categoryId={id} searchParams={sp} />
      </div>
    </main>
  );
}
