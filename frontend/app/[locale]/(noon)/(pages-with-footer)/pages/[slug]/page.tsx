import type { Metadata } from "next";
import { notFound } from "next/navigation";
import StaticPageView from "@/src/features/noon/static-page";
import { getStaticPage } from "@/src/features/noon/static-page/api/static-page.actions";
import { localized } from "@/src/features/noon/static-page/helpers/localized";
import getLocale from "@/src/helpers/getLocale";

type Props = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const [page, locale] = await Promise.all([getStaticPage(slug), getLocale()]);

  if (!page) return {};

  const title =
    page.seo.title[locale] || localized(page.title, locale);
  const description = localized(page.seo.description, locale) || undefined;

  return {
    title,
    description,
    openGraph: { title, description, type: "website" },
  };
}

export default async function StaticPagePage({ params }: Props) {
  const { slug } = await params;
  const page = await getStaticPage(slug);

  if (!page) notFound();

  return <StaticPageView page={page} />;
}
