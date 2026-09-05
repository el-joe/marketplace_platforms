import { notFound } from "next/navigation";
import MarketerProfileView from "@/src/features/noon/marketer-profile";
import { getMarketerProfile, MarketerProfileNotFoundError } from "@/src/features/noon/marketer-profile/api";

type Props = {
  params: Promise<{ locale: string; slug: string }>;
};

export async function generateMetadata({ params }: Props) {
  const { slug } = await params;

  try {
    const data = await getMarketerProfile(slug);
    return {
      title: data.marketer?.name ? `${data.marketer.name} | نون` : "ماركتر | نون",
      description: data.profile?.bio_en ?? "تسوّق منتجات مختارة من هذا الماركتر على نون",
    };
  } catch {
    return { title: "ماركتر | نون" };
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
