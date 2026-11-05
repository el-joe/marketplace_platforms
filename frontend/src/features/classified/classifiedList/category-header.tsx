import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import getLocale from "@/src/helpers/getLocale";
import { IClassifiedBrowseCategory } from "./helpers/types";

type Props = {
  category: IClassifiedBrowseCategory;
};

/** Banner of a category landing page: configured image, description and sub-categories. */
export default async function CategoryHeader({ category }: Props) {
  const t = await getTranslations("classified");
  const locale = await getLocale();
  const description = category.description?.[locale];

  if (!category.image_url && !description && !category.children.length) {
    return null;
  }

  return (
    <section className="mb-4 flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-3 sm:flex-row sm:items-center sm:p-4">
      {category.image_url && (
        <div className="relative aspect-16/9 w-full shrink-0 overflow-hidden rounded-lg bg-gray-100 sm:aspect-auto sm:h-28 sm:w-48">
          <Image
            src={category.image_url}
            alt={category.name[locale]}
            fill
            sizes="(max-width: 640px) 100vw, 192px"
            className="object-cover"
          />
        </div>
      )}
      <div className="min-w-0 flex-1">
        {description && (
          <p className="text-sm leading-relaxed text-gray-600">{description}</p>
        )}
        {category.children.length > 0 && (
          <nav
            aria-label={t("subCategories")}
            className="mt-2 flex flex-wrap gap-2"
          >
            {category.children.map((child) => (
              <Link
                key={child.id}
                href={`/classified/${child.slug}`}
                className="rounded-md border border-blue-200/80 bg-white px-3 py-1.5 text-xs font-medium text-blue-600 transition-colors hover:border-blue-400 hover:bg-blue-50/30"
              >
                {child.name[locale]}
              </Link>
            ))}
          </nav>
        )}
      </div>
    </section>
  );
}
