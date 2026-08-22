import CaseListPage from "@/src/features/noon/profile/support/case-list";
import { getTickets } from "@/src/features/noon/profile/support/api/support.actions";

export default async function TicketsPage() {
  const tickets = await getTickets();

  return <CaseListPage mode="ticket" data={tickets} />;
}
