"use client";
import Image from "next/image";
import { ICategoryNavTree } from "./types";
import { useQueryState } from "nuqs";
import { getImageURL } from "@/src/helpers/get-image-url";
import useLocale from "@/src/hooks/use-locale";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/src/components/ui/accordion";

type Props = {
  categories: ICategoryNavTree[];
};

export default function MainContent({ categories }: Props) {
  const [selectedCategoryId, setSelectedCategoryId] = useQueryState(
    "category",
    { history: "replace" },
  );
  const t = useTranslations("nawyOptions");
  const locale = useLocale();
  const selectedCategory = categories.find(
    (category) => category.id === selectedCategoryId,
  );
  const hasNoChildren: ICategoryNavTree[] =
    selectedCategory?.children?.filter(
      (category) => !category.children?.length,
    ) ?? [];
  const hasChildren: ICategoryNavTree[] =
    selectedCategory?.children?.filter(
      (category) => category.children?.length,
    ) ?? [];
  return (
    <div className="pt-4 flex-1">
      {/* category header */}
      {!selectedCategory ? (
        <h3 className="font-bold">{t("recentlyViewedCategories")}</h3>
      ) : (
        <h3 className="font-bold">{selectedCategory.name?.[locale]}</h3>
      )}
      {!selectedCategory && <h3 className="font-bold">{t("nawyToPPicks")}</h3>}
      {/* category items */}
      <div className="flex gap-2 flex-wrap mt-2">
        {/* show all categories when none is selected */}
        {!selectedCategory &&
          categories.map((category) => (
            <CategoryItem key={category.id} category={category} />
          ))}
        {/* show selected category */}
        {/* category image */}
        {!!selectedCategory?.image_url && (
          <Image
            src={getImageURL(selectedCategory.image_url)}
            alt={selectedCategory.name?.[locale]}
            width={1280}
            height={460}
            className="aspect-16/4 object-cover rounded mx-auto"
          />
        )}
        {/* no child categories */}
        {selectedCategory &&
          !!hasNoChildren.length &&
          hasNoChildren?.map((category) => (
            <CategoryItem key={category.id} category={category} />
          ))}
        {/* child categories */}
        {selectedCategory && !!hasChildren.length && (
          <Accordion className="not-last:border-b-0">
            {hasChildren?.map((child) => (
              <AccordionItem
                key={child.id}
                value={child.id}
                className="w-full not-last:border-b-0"
              >
                <AccordionTrigger className="w-full border-b-0">
                  <span className="text-sm font-medium border-b border-border block py-2">
                    {child.name?.[locale]}
                  </span>
                </AccordionTrigger>
                <AccordionContent className="w-full">
                  <div className="flex gap-2 flex-wrap mt-2">
                    {child?.children?.map((category) => (
                      <CategoryItem key={category.id} category={category} />
                    ))}
                  </div>
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        )}
      </div>
    </div>
  );
}

const CategoryItem = ({ category }: { category: ICategoryNavTree }) => {
  const locale = useLocale();
  return (
    <Link
      href={`/${category?.slug}?nawy_listing=true`}
      className="w-[calc((100%-16px)/3)] flex flex-col items-center gap-2"
    >
      <Image
        src={getImageURL(category?.image_url)}
        alt={category?.name?.[locale]}
        width={360}
        height={200}
        className="w-full aspect-18/14 object-cover rounded"
      />
      <p className="text-[9px] sm:text-sm md:text-base font-medium text-center">
        {category?.name?.[locale]}
      </p>
    </Link>
  );
};
