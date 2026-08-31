import { notFound } from "next/navigation";
import PackageDetails from "@/src/features/flights/package-details";
import { getTravelPackageBySlug } from "@/src/features/flights/api/travel-packages.actions";

type Props = {
  params: Promise<{ slug: string }>;
};

export default async function PackageDetailsPage({ params }: Props) {
  const { slug } = await params;

  const pkg = await getTravelPackageBySlug(slug);

  if (!pkg) notFound();

  return <PackageDetails pkg={pkg} />;
}
