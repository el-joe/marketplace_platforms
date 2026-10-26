import { getTranslations } from "next-intl/server";
import MarketersListView from "@/src/features/noon/marketers-list";
import { Suspense } from "react";
import MarketersSkeleton from "@/src/features/noon/marketers-list/marketers-skeleton";

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

  return (
    <Suspense fallback={<MarketersSkeleton />}>
      <MarketersListView {...params} />
    </Suspense>
  );
}
