import type { Metadata } from "next";
import Header from "@/src/layout/Header";
import MobileNav from "@/src/layout/noon/MobileNav";
import AnnouncementBanner from "@/src/components/shared/AnnouncementBanner";
import { fetchInstance } from "@/src/lib/utils";

export const metadata: Metadata = {
  title: "noon",
  description: "my e-commerce",
};

type BarData = {
  id: string;
  image_url: string;
  cta_url: string | null;
};

async function getAnnouncementBar(): Promise<BarData | null> {
  try {
    const res = await fetchInstance<{
      success: boolean;
      data: { data: BarData[] };
    }>("/announcement-bars", { next: { revalidate: 300 } });

    const bar = res?.data?.data?.[0] ?? null;

    if (!bar?.image_url) return null;

    return bar;
  } catch {
    return null;
  }
}

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const bar = await getAnnouncementBar();

  return (
    <>
      {bar && (
        <AnnouncementBanner
          id={bar.id}
          image_url={bar.image_url}
          cta_url={bar.cta_url}
        />
      )}
      <Header />
      <main className={bar ? "md:pt-40" : "md:pt-26"}>{children}</main>
      {/* mobile nav only showing for small screens (width <= 768px) */}
      <MobileNav />
    </>
  );
}
