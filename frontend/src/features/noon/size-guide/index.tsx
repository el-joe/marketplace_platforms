import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import getLocale from "@/src/helpers/getLocale";
import SizeGuideView from "@/src/components/shared/size-guide/size-guide-view";
import { SizeGuide } from "@/src/components/shared/size-guide/types";

/** Standalone "size map" page for a vendor size guide. */
export default async function SizeGuidePage({ guide }: { guide: SizeGuide }) {
  const t = await getTranslations("sizeGuide");
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  return (
    <div className="container max-w-4xl py-6 md:py-10 flex flex-col gap-6">
      <header className="flex flex-col gap-1">
        <h1 className="text-xl md:text-3xl font-bold">
          {guide.title[lang] || guide.title.en}
        </h1>
        {guide.vendor && (
          <p className="text-sm text-gray">
            {t("by")}{" "}
            <Link href={`/seller/${guide.vendor.id}`} className="text-blue underline underline-offset-4">
              {guide.vendor.store_name}
            </Link>
          </p>
        )}
      </header>
      <SizeGuideView guide={guide} />
      <p className="text-xs text-gray">{t("tailoredNote")}</p>
    </div>
  );
}
