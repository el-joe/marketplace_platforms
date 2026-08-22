import type { Metadata } from "next";
import Header from "@/src/layout/Header";
import MobileNav from "@/src/layout/noon/MobileNav";

export const metadata: Metadata = {
  title: "noon",
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
      {/* mobile nav only showing for small screens (width <= 768px) */}
      <MobileNav />
    </>
  );
}
