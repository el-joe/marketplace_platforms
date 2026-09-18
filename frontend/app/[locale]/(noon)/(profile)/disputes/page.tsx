import CaseListPage from "@/src/features/noon/profile/support/case-list";
import { getDisputes } from "@/src/features/noon/profile/support/api/support.actions";

export default async function DisputesPage() {
  const disputes = await getDisputes();


  return <CaseListPage mode="dispute" data={disputes} />;
}
