import ClassifiedsList from "@/src/features/classified/classifiedList";
import React from "react";

type Props = {
  params: Promise<{ categoryId: string }>;
};

export default async function page({ params }: Props) {
  const { categoryId } = await params;
  return <ClassifiedsList categoryId={categoryId} />;
}
