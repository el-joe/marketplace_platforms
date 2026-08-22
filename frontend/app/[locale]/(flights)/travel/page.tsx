import TravelPackagesListing from "@/src/features/flights/travel-packages";

export default async function TravelPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; category?: string }>;
}) {
  const params = await searchParams;
  return <TravelPackagesListing searchParams={params} />;
}
