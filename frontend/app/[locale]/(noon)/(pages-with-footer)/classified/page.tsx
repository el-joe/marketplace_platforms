import React from "react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import ClassifiedsList from "@/src/features/classified/classifiedList";

type Props = {
  params: Promise<{ locale: string }>;
  searchParams: Promise<Record<string, string>>;
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations("classified");

  return {
    title: t("metaTitleAll"),
    description: t("metaDescriptionAll"),
    alternates: { canonical: `/${locale}/classified` },
  };
}

export default async function ClassifiedPage({ searchParams }: Props) {
  return <ClassifiedsList location={await searchParams} />;
}
