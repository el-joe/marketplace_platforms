import { MarketerCard } from "./api";
import MarketersHero from "./components/marketers-hero";
import MarketersTabs from "./components/marketers-tabs";
import MarketersGrid from "./components/marketers-grid";

interface Props {
  marketers: MarketerCard[];
  total: number;
  activeType?: string;
}

export default async function MarketersListView({ marketers, total, activeType }: Props) {
  return (
    <div className="container mx-auto px-4 py-6 sm:py-8">
      <MarketersHero marketers={marketers} total={total} />

      <div className="my-8 flex justify-center">
        <MarketersTabs activeType={activeType} />
      </div>

      <MarketersGrid marketers={marketers} />
    </div>
  );
}
