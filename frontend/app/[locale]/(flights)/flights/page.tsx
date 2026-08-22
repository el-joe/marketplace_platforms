import TravelPackagesListing from "@/src/features/flights/travel-packages";

export default async function FlightsPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string }>;
}) {
  const params = await searchParams;
  return <TravelPackagesListing searchParams={params} />;
}
