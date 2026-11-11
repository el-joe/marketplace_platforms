import { notFound } from "next/navigation";
import type { Metadata } from "next";
import SizeGuidePage from "@/src/features/noon/size-guide";
import { getSizeGuide } from "@/src/features/noon/size-guide/api/get-size-guide";
import getLocale from "@/src/helpers/getLocale";

type Props = {
  params: Promise<{ id: string }>;
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { id } = await params;
  const guide = await getSizeGuide(id);
  if (!guide) return {};
  const locale = await getLocale();
  return { title: guide.title[locale === "ar" ? "ar" : "en"] || guide.title.en };
}

export default async function SizeGuideRoute({ params }: Props) {
  const { id } = await params;

  const guide = await getSizeGuide(id);

  if (!guide) notFound();

  return <SizeGuidePage guide={guide} />;
}
