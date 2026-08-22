import { notFound } from "next/navigation";
import CaseDetailPage from "@/src/features/noon/profile/support/case-detail";
import { getDisputeById } from "@/src/features/noon/profile/support/api/support.actions";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function DisputeDetailPage({ params }: Props) {
  const { id } = await params;
  const dispute = await getDisputeById(id);

  if (!dispute) notFound();

  return <CaseDetailPage data={{ mode: "dispute", ...dispute }} />;
}
