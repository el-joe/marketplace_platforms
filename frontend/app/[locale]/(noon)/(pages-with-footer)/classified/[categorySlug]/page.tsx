import ClassifiedsList from "@/src/features/classified/classifiedList";
import { getClassifiedCategoryService } from "@/src/features/classified/classifiedList/api/get";
import { ApiRequestError } from "@/src/lib/utils";
import { getTranslations } from "next-intl/server";
import { notFound, permanentRedirect } from "next/navigation";
import type { Metadata } from "next";
import React, { cache } from "react";

type Props = {
  params: Promise<{ categorySlug: string; locale: string }>;
  searchParams: Promise<Record<string, string>>;
};

/**
 * Resolves the category once per request (metadata + page). A category that is disabled, whose parent is
 * disabled or that is not offered in the visitor's country is a 404; the API answers 404 for all of them.
 */
const getCategory = cache(async (idOrSlug: string) => {
  try {
    const res = await getClassifiedCategoryService(idOrSlug);
    return res.data.category ?? null;
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) return null;
    throw error;
  }
});

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { categorySlug, locale } = await params;
  const category = await getCategory(categorySlug);
  const t = await getTranslations("classified");

  if (!category) return { title: t("metaTitleAll"), robots: { index: false } };

  const lang = locale.endsWith("-ar") ? "ar" : "en";
  const name = category.name[lang];
  const title = t("metaTitle", { category: name });
  const description =
    category.description?.[lang] || t("metaDescription", { category: name });

  return {
    title,
    description,
    alternates: { canonical: `/${locale}/classified/${category.slug}` },
    openGraph: {
      title,
      description,
      ...(category.image_url ? { images: [category.image_url] } : {}),
    },
  };
}

export default async function page({ params, searchParams }: Props) {
  const { categorySlug, locale } = await params;
  const search = await searchParams;
  const category = await getCategory(categorySlug);

  if (!category) notFound();

  // Old links use the category id: send them (and renamed-case variants) to the canonical slug URL.
  if (category.slug !== categorySlug) {
    const qs = new URLSearchParams(search).toString();
    permanentRedirect(
      `/${locale}/classified/${category.slug}${qs ? `?${qs}` : ""}`,
    );
  }

  return (
    <ClassifiedsList
      categorySlug={category.slug}
      category={category}
      location={search}
    />
  );
}
