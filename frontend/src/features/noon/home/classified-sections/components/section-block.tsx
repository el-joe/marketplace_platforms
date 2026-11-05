import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import getLocale from "@/src/helpers/getLocale";
import { IClassifiedSection } from "@/src/features/classified/classifiedList/helpers/types";
import SectionAdCard from "./section-ad-card";

type Props = {
  section: IClassifiedSection;
};

/** One admin-configured classified section: header with link to its landing page and its newest ads. */
export default async function SectionBlock({ section }: Props) {
  const t = await getTranslations("classified");
  const locale = await getLocale();
  const href = `/classified/${section.slug}`;

  return (
    <section>
      <div className="mb-3 flex items-center justify-between gap-3">
        <div className="flex min-w-0 items-center gap-3">
          {section.image_url && (
            <Image
              src={section.image_url}
              alt=""
              width={40}
              height={40}
              className="size-10 shrink-0 rounded-lg object-cover"
            />
          )}
          <div className="min-w-0">
            <h2 className="truncate text-lg font-bold text-gray-900 sm:text-xl">
              <Link href={href}>{section.name[locale]}</Link>
            </h2>
            <p className="text-xs text-gray-500">
              {t("sectionAdsCount", { count: section.total.toLocaleString() })}
            </p>
          </div>
        </div>
        <Link
          href={href}
          className="shrink-0 text-sm font-medium text-blue-600 hover:underline"
        >
          {t("viewAllAds")}
        </Link>
      </div>
      <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        {section.listings.map((listing) => (
          <li key={listing.listing_id}>
            <SectionAdCard listing={listing} locale={locale} />
          </li>
        ))}
      </ul>
    </section>
  );
}
