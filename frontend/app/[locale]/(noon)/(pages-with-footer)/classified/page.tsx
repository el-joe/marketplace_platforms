import React from "react";
import ClassifiedsList from "@/src/features/classified/classifiedList";

type Props = {
  searchParams: Promise<Record<string, string>>;
};

export default async function ClassifiedPage({ searchParams }: Props) {
  return <ClassifiedsList location={await searchParams} />;
}
