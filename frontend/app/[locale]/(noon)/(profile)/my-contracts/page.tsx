import CustomerContracts from "@/src/features/noon/profile/customer-contracts";

type Props = {
  searchParams: Promise<Record<string, string>>;
};

export default async function MyContractsPage({ searchParams }: Props) {
  const sp = await searchParams;

  return <CustomerContracts page={Number(sp.page) || 1} />;
}
