import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import MarketerProfileView from "@/src/features/noon/marketer-profile";
import {
  getMarketerProfile,
  MarketerProfileNotFoundError,
} from "@/src/features/noon/marketer-profile/api";
import getLocale from "@/src/helpers/getLocale";

type Props = {
  params: Promise<{ locale: string; slug: string }>;
};

export async function generateMetadata({ params }: Props) {
  const { slug } = await params;
  const t = await getTranslations("marketerProfile");
  const locale = await getLocale();

  try {
    const data = await getMarketerProfile(slug);
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

export default async function MarketerProfilePage({ params }: Props) {
  const { slug } = await params;

  let profileData;
  try {
    profileData = await getMarketerProfile(slug);
  } catch (error) {
    if (error instanceof MarketerProfileNotFoundError) {
      notFound();
    }
    throw error;
  }

  return <MarketerProfileView data={profileData} />;
}
