"use client";
import { RulerIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import useLocale from "@/src/hooks/use-locale";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/src/components/ui/sheet";
import SizeGuideView from "./size-guide-view";
import { SizeGuide } from "./types";

/** "Size guide" link on the PDP that opens the vendor's size map in a side sheet. */
export default function SizeGuideSheet({ guide }: { guide: SizeGuide }) {
  const t = useTranslations("sizeGuide");
  const locale = useLocale();

  return (
    <Sheet>
      <SheetTrigger
        render={
          <button
            type="button"
            className="inline-flex items-center gap-1.5 text-sm font-medium text-blue underline underline-offset-4"
          />
        }
      >
        <RulerIcon className="size-4" />
        {t("open")}
      </SheetTrigger>
      <SheetContent
        side={locale === "ar" ? "left" : "right"}
        className="w-full sm:max-w-xl overflow-y-auto p-4"
      >
        <SheetHeader className="p-0">
          <SheetTitle>{guide.title[locale] || guide.title.en}</SheetTitle>
        </SheetHeader>
        <SizeGuideView guide={guide} />
        <Link
          href={`/size-guide/${guide.id}`}
          target="_blank"
          className="text-sm text-blue underline underline-offset-4 self-start"
        >
          {t("openFullPage")}
        </Link>
      </SheetContent>
    </Sheet>
  );
}
