import type { Metadata } from "next";
// import Header from "@/src/layout/Header";
import MobileNav from "@/src/layout/noon/MobileNav";
import Header from "@/src/layout/noon/header/Header";

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
      <MobileNav />
    </>
  );
}
