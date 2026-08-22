import ClassifiedListingView from "@/src/features/open-sooq/listingView";

type Props = {
  params: Promise<{ slug: string }>;
};

export default async function ClassifiedListingPage({ params }: Props) {
  const { slug } = await params;
  return <ClassifiedListingView slug={slug} />;
}
