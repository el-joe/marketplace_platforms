"use client";

import { SlidersHorizontalIcon } from "lucide-react";
import { useTranslations } from "next-intl";

import { Button } from "@/src/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/src/components/ui/sheet";
import useLocale from "@/src/hooks/use-locale";

import FilterSidebar from "./desktop-view";

const MobileFiltersSheet = () => {
  const t = useTranslations("shop");
  const locale = useLocale();
  const side = locale === "ar" ? "right" : "left";

  return (
    <Sheet>
      <SheetTrigger
        render={
          <Button variant="outline" className="lg:hidden gap-1.5">
            <SlidersHorizontalIcon className="size-4" />
            {t("filters")}
          </Button>
        }
      />
      <SheetContent side={side} className="overflow-y-auto p-4">
        <SheetHeader className="p-0">
          <SheetTitle>{t("filters")}</SheetTitle>
        </SheetHeader>
        <FilterSidebar />
      </SheetContent>
    </Sheet>
  );
};

export default MobileFiltersSheet;
