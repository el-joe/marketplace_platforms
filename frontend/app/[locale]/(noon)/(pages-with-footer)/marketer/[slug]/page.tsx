import MarketerProfile from "@/src/features/noon/marketer-profile";
import MarketersSkeleton from "@/src/features/noon/marketers-list/marketers-skeleton";
import { getTranslations } from "next-intl/server";
import React, { Suspense } from "react";

export async function generateMetadata() {
  const t = await getTranslations("marketerProfile");

  return {
    title: t("pageTitleFallback"),
    description: t("pageDescriptionFallback"),
  };
}

type props = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<Record<string, string>>;
};

export default async function page({ params, searchParams }: props) {
  const resolvedParams = await params;
  const resolvedSearchParams = await searchParams;
  const slug = resolvedParams.slug;
  return (
    <Suspense fallback={<MarketersSkeleton />}>
      <MarketerProfile slug={slug} params={resolvedSearchParams} />
    </Suspense>
  );
}
