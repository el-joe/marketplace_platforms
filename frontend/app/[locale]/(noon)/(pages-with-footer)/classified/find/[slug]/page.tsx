import React from "react";
import ClassifiedView from "@/src/features/classified/classified-view";

type Props = {
  params: Promise<{ slug: string; locale: string }>;
};

export default async function ClassifiedDetailPage({ params }: Props) {
  await params;

  return <ClassifiedView />;
}
