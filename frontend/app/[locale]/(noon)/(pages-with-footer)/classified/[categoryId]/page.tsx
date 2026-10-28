import ClassifiedsList from "@/src/features/classified/classifiedList";
import React from "react";

type Props = {
  params: Promise<{ categoryId: string }>;
  searchParams: Promise<Record<string, string>>;
};

export default async function page({ params, searchParams }: Props) {
  const { categoryId } = await params;
  return (
    <ClassifiedsList categoryId={categoryId} location={await searchParams} />
  );
}
