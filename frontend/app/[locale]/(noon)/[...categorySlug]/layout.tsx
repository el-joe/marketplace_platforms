import FilterSidebar from "@/src/features/noon/shop/filter/desktop-view";

const ShopLayout = ({ children }: { children: React.ReactNode }) => {
  return (
    <main className="container flex flex-col gap-4 py-4 lg:flex-row lg:gap-6">
      <aside className="w-[250px] h-[calc(100vh-100px)] sticky top-[100px] overflow-y-auto scrollbar-hide">
        <FilterSidebar />
      </aside>
      <div className="min-w-0 flex-1">{children}</div>
    </main>
  );
};

export default ShopLayout;
