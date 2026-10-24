import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import MarketerProfileView from "@/src/features/noon/marketer-profile";
import {
  getMarketerProfile,
  MarketerProfileNotFoundError,
} from "@/src/features/noon/marketer-profile/api";
import { parseFilters } from "@/src/features/noon/marketer-profile/helpers/filters";
import getLocale from "@/src/helpers/getLocale";

type Props = {
  params: Promise<{ locale: string; slug: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export async function generateMetadata({ params, searchParams }: Props) {
  const { slug } = await params;
  const filters = parseFilters(await searchParams);
  const t = await getTranslations("marketerProfile");
  const locale = await getLocale();

  try {
    const data = await getMarketerProfile(slug, filters);
    const bio = locale === "ar" ? data.profile?.bio_ar : data.profile?.bio_en;
    return {
      title: data.marketer?.name
        ? `${data.marketer.name} | ${t("brandName")}`
        : t("pageTitleFallback"),
      description: bio ?? t("pageDescriptionFallback"),
    };
  } catch {
    return { title: t("pageTitleFallback") };
  }
}

export default async function MarketerProfilePage({ params, searchParams }: Props) {
  const { slug } = await params;
  const filters = parseFilters(await searchParams);

  let profileData;
  try {
    profileData = await getMarketerProfile(slug, filters);
  } catch (error) {
    if (error instanceof MarketerProfileNotFoundError) {
      notFound();
    }
    throw error;
  }

  return <MarketerProfileView data={profileData} filters={filters} />;
}
