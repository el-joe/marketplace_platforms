import SpecialRequestDetail from "@/src/features/noon/profile/special-requests/detail";

export default async function SpecialRequestDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <SpecialRequestDetail id={id} />;
}
