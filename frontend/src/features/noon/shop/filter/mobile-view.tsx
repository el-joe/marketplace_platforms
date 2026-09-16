"use client";

import { SlidersHorizontalIcon, XIcon } from "lucide-react";
import { useTranslations } from "next-intl";

import { Button } from "@/src/components/ui/button";
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/src/components/ui/sheet";

import FilterSidebar from "./desktop-view";
import { Facets } from "../types";

type FilterSidebarProps = {
  facets?: Facets | null;
  locale: string;
};

const MobileFiltersSheet = ({ facets, locale }: FilterSidebarProps) => {
  const t = useTranslations("shop");
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
      <SheetContent
        side={side}
        showCloseButton={false}
        className="overflow-y-auto p-4"
      >
        <SheetHeader className="sticky top-0 z-10 flex-row items-center justify-between bg-popover p-0">
          <SheetTitle>{t("filters")}</SheetTitle>
          <SheetClose render={<Button variant="ghost" size="icon-sm" />}>
            <XIcon />
            <span className="sr-only">{t("close")}</span>
          </SheetClose>
        </SheetHeader>
        <FilterSidebar locale={locale} facets={facets} />
      </SheetContent>
    </Sheet>
  );
};

export default MobileFiltersSheet;
