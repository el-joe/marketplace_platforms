import { notFound } from "next/navigation";
import CaseDetailPage from "@/src/features/noon/profile/support/case-detail";
import { getTicketById } from "@/src/features/noon/profile/support/api/support.actions";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function TicketDetailPage({ params }: Props) {
  const { id } = await params;

  const ticket = await getTicketById(id);

  if (!ticket) notFound();

  return <CaseDetailPage data={{ mode: "ticket", ...ticket }} />;
}
