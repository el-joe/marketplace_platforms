import { getTranslations } from "next-intl/server";
import MarketersListView from "@/src/features/noon/marketers-list";
import { parseListSearchParams } from "@/src/features/noon/marketers-list/helpers/search-params";

export async function generateMetadata() {
  const t = await getTranslations("marketers");

  return {
    title: t("pageTitle"),
    description: t("pageDescription"),
  };
}

type Props = {
  searchParams: Promise<{ gender?: string; type?: string; page?: string }>;
};

export default async function MarketersPage({ searchParams }: Props) {
  const params = parseListSearchParams(await searchParams);

  return <MarketersListView {...params} />;
}
