import React from "react";
import { getMarketersBanners, getMarketersList } from "./api";
import GenderTabs from "./gender-tabs";
import MarketersList from "./marketers-list";
import { IMarketerItem } from "./helpers/types";
import { getLocale } from "next-intl/server";
import { redirect } from "next/navigation";
import HeroBanner from "./hero-banner";
import StripBanners from "./strip-banners";
import EmptyState from "./empty-state";
import Pagination from "@/src/components/shared/pagination";

type Props = {
  gender?: string;
  type?: string;
  page?: string;
};

export default async function MarketersListView({ gender, type, page }: Props) {
  if (!gender) {
    redirect("/marketers?gender=female");
  }
  const searchParams = new URLSearchParams({
    gender: gender ?? "",
    type: type ?? "",
    page: page ?? "",
  });

  const [marketersList, bannersRes] = await Promise.all([
    getMarketersList(searchParams),
    getLocale().then((locale) => getMarketersBanners(gender, locale)),
  ]);
  return (
    <div className="container">
      <HeroBanner banners={bannersRes.data.banners.hero} />
      <StripBanners banners={bannersRes.data.banners.strip} />
      <GenderTabs active={gender ?? ""} />
      {!marketersList.data.items.length && <EmptyState />}
      {Object.entries(
        marketersList.data.items.reduce<Record<string, IMarketerItem[]>>(
          (acc, m) => {
            (acc[m.type] ??= []).push(m);
            return acc;
          },
          {},
        ),
      ).map(([key, value]) => (
        <MarketersList key={key} list={value} type={key} />
      ))}
      {!!type && <Pagination totalPages={marketersList.data.meta.last_page} />}
    </div>
  );
}
