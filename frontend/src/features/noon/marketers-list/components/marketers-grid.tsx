import { getTranslations } from "next-intl/server";
import { Users } from "lucide-react";
import { MarketerCard as MarketerCardData } from "../api";
import { getFeaturedMarketer } from "../helpers/get-featured-marketer";
import MarketerCard from "./marketer-card";
import FeaturedMarketerCard from "./featured-marketer-card";

interface Props {
  marketers: MarketerCardData[];
}

export default async function MarketersGrid({ marketers }: Props) {
  const t = await getTranslations("marketers");

  if (marketers.length === 0) {
    return (
      <div className="flex flex-col items-center gap-3 py-24 text-center">
        <div className="flex h-14 w-14 items-center justify-center rounded-full bg-gray-2">
          <Users className="h-6 w-6 text-gray" />
        </div>
        <p className="text-sm font-semibold text-gray">{t("emptyState")}</p>
      </div>
    );
  }

  const { featured, rest } = getFeaturedMarketer(marketers);

  return (
    <div className="grid auto-rows-[10.5rem] grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
      {featured && <FeaturedMarketerCard marketer={featured} />}
      {rest.map((marketer, index) => (
        <MarketerCard key={marketer.id} marketer={marketer} index={index} />
      ))}
    </div>
  );
}
