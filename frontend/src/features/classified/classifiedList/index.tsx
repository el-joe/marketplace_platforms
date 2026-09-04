import TopFilterBar from "./top-filter-bar";
import BreadcrumbAndHeader from "./breadcrumb-and-header";
import CategoryTags from "./category-tags";
import ActiveFiltersBar from "./active-filters-bar";
import ClassifiedCard from "./classified-card";
import ClassifiedSidebar from "./classified-sidebar";
import {
  getClassifiedCategoriesService,
  getClassifiedsService,
} from "./api/get";

type ClassifiedsListProps = {
  categoryId?: string;
};

export default async function ClassifiedsList({
  categoryId,
}: ClassifiedsListProps) {
  const { data } = await getClassifiedsService({
    category: categoryId || "all",
  });
  const { data: categories } = await getClassifiedCategoriesService();

  const classifiedCategories = categories?.filter(
    (cat) => cat.type === "classified",
  );
  const selectedCategory = categories?.find((cat) => cat.id === categoryId);
  // const [listings, setListings] = useState(MOCK_LISTINGS);

  // const handleSortChange = (sort: string) => {
  //   let sorted = [...listings];
  //   if (sort === "price_asc") {
  //     sorted.sort((a, b) => a.price - b.price);
  //   } else if (sort === "price_desc") {
  //     sorted.sort((a, b) => b.price - a.price);
  //   } else {
  //     sorted = [...MOCK_LISTINGS];
  //   }
  //   setListings(sorted);
  // };

  return (
    <div className="bg-[#f8f9fa] min-h-screen py-4 sm:py-6">
      <div className="container">
        {/* Top 4-dropdown filter bar */}
        <TopFilterBar />

        {/* Breadcrumb, Page Title & Sort Selector */}
        <BreadcrumbAndHeader
          totalCount={data?.listings?.meta?.total}
          // onSortChange={handleSortChange}
          category={selectedCategory || null}
        />

        {/* Category Pills Bar */}
        <CategoryTags
          categories={classifiedCategories}
          selectedCtg={categoryId || null}
        />

        {/* Main 2-Column Responsive Layout */}
        <div className="flex flex-col lg:flex-row gap-6 items-start">
          {/* Sidebar Column (renders on Left in LTR, Right in RTL) */}
          <ClassifiedSidebar
            categories={classifiedCategories}
            selectedCtg={categoryId || null}
          />

          {/* Main Listings Column */}
          <main className="flex-1 w-full min-w-0">
            {/* Active Filters Tag Bar */}
            <ActiveFiltersBar selectedCtg={selectedCategory || null} />
            {data?.listings?.items.map((listing) => (
              <ClassifiedCard key={listing.listing_id} listing={listing} />
            ))}
          </main>
        </div>
      </div>
    </div>
  );
}
