import Orders from "@/src/features/noon/profile/orders";

type Props = {
  searchParams: Promise<Record<string, string>>;
};

export default async function OrdersPage({ searchParams }: Props) {
  const sp = await searchParams;

  return <Orders status={sp.status} />;
}
