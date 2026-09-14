import { getTranslations } from "next-intl/server";
import MarketersListView from "@/src/features/noon/marketers-list";
import { getMarketersList } from "@/src/features/noon/marketers-list/api";

export async function generateMetadata() {
  const t = await getTranslations("marketers");

  return {
    title: t("pageTitle"),
    description: t("pageDescription"),
  };
}

type Props = {
  searchParams: Promise<{ type?: string }>;
};

export default async function MarketersPage({ searchParams }: Props) {
  const { type } = await searchParams;
  const { items, meta } = await getMarketersList({ type });

  return <MarketersListView marketers={items} total={meta.total} activeType={type} />;
}
