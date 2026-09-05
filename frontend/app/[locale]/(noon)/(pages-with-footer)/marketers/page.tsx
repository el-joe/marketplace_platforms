import MarketersListView from "@/src/features/noon/marketers-list";
import { getMarketersList } from "@/src/features/noon/marketers-list/api";

export const metadata = {
  title: "الماركترز | نون",
  description: "تسوّق من صفحات المؤثرين والماركترز على نون",
};

type Props = {
  searchParams: Promise<{ type?: string }>;
};

export default async function MarketersPage({ searchParams }: Props) {
  const { type } = await searchParams;
  const { items, meta } = await getMarketersList({ type });

  return <MarketersListView marketers={items} total={meta.total} activeType={type} />;
}
