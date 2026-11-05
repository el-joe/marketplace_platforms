import { notFound } from "next/navigation";
import CustomerContractDetail from "@/src/features/noon/profile/customer-contracts/detail";
import { getMyContract } from "@/src/services/customer-contracts";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function MyContractDetailPage({ params }: Props) {
  const { id } = await params;
  const contract = await getMyContract(id);

  if (!contract) notFound();

  return <CustomerContractDetail contract={contract} />;
}
