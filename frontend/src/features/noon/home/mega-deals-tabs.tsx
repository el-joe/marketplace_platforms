"use client";
import React, { useState } from "react";
import MegaDealsCard from "@/src/components/shared/MegaDealsCard";
import useLocale from "@/src/hooks/use-locale";
import { cn } from "@/src/lib/utils";
import { ColumnTab } from "./types";

const MegaDealsTabs = ({ tabs }: { tabs: ColumnTab[] }) => {
  const locale = useLocale();
  const [activeIndex, setActiveIndex] = useState(0);
  const activeTab = tabs[activeIndex];

  return (
    <div>
      {tabs.length > 1 && (
        <div className="flex items-center gap-2 mb-4 overflow-x-auto">
          {tabs.map((tab, i) => (
            <button
              key={i}
              type="button"
              onClick={() => setActiveIndex(i)}
              className={cn(
                "px-3 py-1.5 rounded-md text-sm whitespace-nowrap border",
                i === activeIndex
                  ? "bg-gray text-white border-gray"
                  : "bg-transparent text-gray border-gray-2",
              )}
            >
              {locale === "ar" ? tab.label?.ar : tab.label?.en}
            </button>
          ))}
        </div>
      )}
      <div className="flex gap-4 flex-wrap">
        {activeTab?.products?.map((deal) => (
          <MegaDealsCard key={deal.slug} data={deal} />
        ))}
      </div>
    </div>
  );
};

export default MegaDealsTabs;
