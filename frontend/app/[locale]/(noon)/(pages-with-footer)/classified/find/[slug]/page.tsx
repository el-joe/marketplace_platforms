import React from "react";
import { notFound } from "next/navigation";
import ClassifiedView from "@/src/features/classified/classified-view";
import {
  getClassifiedDetailsService,
  getRelatedClassifiedService,
} from "@/src/features/classified/classified-view/api/get";
import { toClassifiedDetail } from "@/src/features/classified/classified-view/helpers/to-classified-detail";

type Props = {
  params: Promise<{ slug: string; locale: string }>;
};

export default async function ClassifiedDetailPage({ params }: Props) {
  const { slug, locale } = await params;

  const detailResponse = await getClassifiedDetailsService(slug).catch(
    () => null,
  );

  if (!detailResponse?.data) notFound();

  const relatedResponse = await getRelatedClassifiedService(slug).catch(
    () => null,
  );

  const initialData = toClassifiedDetail(
    detailResponse.data,
    relatedResponse?.data?.items ?? [],
    locale,
  );

  return <ClassifiedView initialData={initialData} />;
}
