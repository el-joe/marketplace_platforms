"use client";
import React, { useState } from "react";
import { Button } from "@/src/components/ui/button";
import { ChevronLeft, ChevronRight, MenuIcon } from "lucide-react";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/src/components/ui/sheet";
import Logo from "@/src/components/shared/Logo";
import { useQuery } from "@tanstack/react-query";
import { getCategoriesTree } from "@/src/services/get";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import { Skeleton } from "@/src/components/ui/skeleton";
import { cn } from "@/src/lib/utils";
import { CategoryNavTree } from "@/types";
import { Type } from "@/types/category-nav-tree.type";
import { useRouter } from "../../../i18n/navigation";

function subCategoryHref(
  child: CategoryNavTree,
  parent?: CategoryNavTree,
): string {
  switch (child.type ?? parent?.type) {
    case Type.ClassiFied:
      return `/classified/${child.id}`;
    case Type.Travel:
      return child.slug ? `/travel?category=${child.slug}` : "/travel";
    case Type.Product:
    default:
      return `/${child.slug}`;
  }
}

const SideCategoriesList = () => {
  const [selectedCategory, setSelectedCategory] = useState<CategoryNavTree[]>(
    [],
  );
  const t = useTranslations("header");
  const locale = useLocale();
  const router = useRouter();
  const { data, isLoading } = useQuery({
    queryKey: ["categoriesTree"],
    queryFn: getCategoriesTree,
  });
  return (
    <Sheet>
      <SheetTrigger
        render={
          <Button variant={"ghost"} className={"p-0"}>
            <MenuIcon className="size-5 md:hidden" />
          </Button>
        }
      />
      <SheetContent side="left" className="overflow-auto">
        <SheetHeader>
          <SheetTitle className={"w-24"}>
            <Logo />
          </SheetTitle>
        </SheetHeader>
        <div className="px-4 pb-8 relative">
          {/* top parent category */}
          <div
            className={cn(
              "transition duration-500",
              !!selectedCategory.length && "translate-x-[calc(-100%_-20px)]",
            )}
          >
            <p className="text-base font-bold mb-3">{t("categories")}</p>
            <ul>
              {isLoading &&
                Array.from({ length: 12 }).map((e, i) => (
                  <li key={i}>
                    <Skeleton className="w-full h-6 my-3" />
                  </li>
                ))}
              {data?.map((category) => (
                <li
                  key={category.id}
                  className="py-3 border-b border-border flex justify-between items-center"
                  onClick={() => {
                    if (!!category.children.length) {
                      setSelectedCategory([category]);
                    } else {
                      router.push(subCategoryHref(category));
                    }
                  }}
                >
                  {category.name[locale]}
                  {!!category.children.length &&
                    (locale === "ar" ? (
                      <ChevronLeft className="size-3" />
                    ) : (
                      <ChevronRight className="size-3" />
                    ))}
                </li>
              ))}
            </ul>
          </div>
          {/* chields category */}
          {selectedCategory.map((s, i) => (
            <div
              key={s.id}
              className={cn(
                "transition duration-500 absolute top-0 px-3 inset-x-0",
                i < selectedCategory.length - 1 &&
                  "translate-x-[calc(-100%_-20px)]",
                i > selectedCategory.length && "translate-x-[calc(100%_+20px)]",
              )}
            >
              <p
                className="text-base font-bold mb-3 flex items-center"
                onClick={() => setSelectedCategory((p) => p.slice(0, -1))}
              >
                {locale == "ar" ? (
                  <ChevronRight className="size-5" />
                ) : (
                  <ChevronLeft className="size-5" />
                )}
                {selectedCategory.slice(-1)[0]?.name[locale]}
              </p>
              <ul>
                {isLoading &&
                  Array.from({ length: 12 }).map((e, i) => (
                    <li key={i}>
                      <Skeleton className="w-full h-6 my-3" />
                    </li>
                  ))}
                {selectedCategory.slice(-1)[0]?.children.map((category) => (
                  <li
                    key={category.id}
                    className="py-3 border-b border-border flex justify-between items-center"
                    onClick={() => {
                      if (!!category.children.length) {
                        setSelectedCategory((p) => [...p, category]);
                      } else {
                        router.push(
                          subCategoryHref(category, selectedCategory.slice(-1)[0]),
                        );
                      }
                    }}
                  >
                    {category.name[locale]}
                    {!!category.children.length &&
                      (locale === "ar" ? (
                        <ChevronLeft className="size-3" />
                      ) : (
                        <ChevronRight className="size-3" />
                      ))}
                  </li>
                ))}
              </ul>
            </div>
          ))}

          {/* <div
            className={cn(
              "transition delay-500",
              selectedCategory.length > 1 && "translate-x-[calc(-100%_-20px)]",
              selectedCategory.length < 1 && "translate-x-[calc(100%_+20px)]",
            )}
          >
            <p className="text-base font-bold">{data?.find(category=>category.id === selectedCategory)}</p>
            <ul>
              {isLoading &&
                Array.from({ length: 12 }).map((e, i) => (
                  <li key={i}>
                    <Skeleton className="w-full h-6 my-3" />
                  </li>
                ))}
              {data?.map((category) => (
                <li
                  key={category.id}
                  className="py-3 border-b border-border flex justify-between items-center"
                  onClick={() => setSelectedCategory([category.id])}
                >
                  {category.name}
                  {locale === "ar" ? (
                    <ChevronLeft className="size-3" />
                  ) : (
                    <ChevronRight className="size-3" />
                  )}
                </li>
              ))}
            </ul>
          </div> */}
        </div>
      </SheetContent>
    </Sheet>
  );
};

export default SideCategoriesList;
