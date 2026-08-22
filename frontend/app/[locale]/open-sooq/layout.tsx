import Footer from "@/src/layout/open-sooq/footer";
import Header from "@/src/layout/open-sooq/Header";
import MobileNav from "@/src/layout/open-sooq/mobile-nav";
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "open-sooq",
  description: "my e-commerce",
};

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <>
      <Header />
      <main className="md:pt-26">{children}</main>
      <MobileNav />
      <Footer />
    </>
  );
}
