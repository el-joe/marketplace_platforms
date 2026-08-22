import CaseListPage from "@/src/features/noon/profile/support/case-list";
import { getDisputes } from "@/src/features/noon/profile/support/data";

export default async function DisputesPage() {
  const disputes = getDisputes();

  return <CaseListPage mode="dispute" data={disputes} />;
}
