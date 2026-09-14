import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import MarketerProfileView from "@/src/features/noon/marketer-profile";
import { getMarketerProfile, MarketerProfileNotFoundError } from "@/src/features/noon/marketer-profile/api";

type Props = {
  params: Promise<{ locale: string; slug: string }>;
};

export async function generateMetadata({ params }: Props) {
  const { slug } = await params;
  const t = await getTranslations("marketerProfile");

  try {
    const data = await getMarketerProfile(slug);
    return {
      title: data.marketer?.name ? `${data.marketer.name} | نون` : t("pageTitleFallback"),
      description: data.profile?.bio_en ?? t("pageDescriptionFallback"),
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
