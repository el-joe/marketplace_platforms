import { getTranslations } from "next-intl/server";
import MarketersListView from "@/src/features/noon/marketers-list";

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
  const params = await searchParams;

  return <MarketersListView {...params} />;
}
