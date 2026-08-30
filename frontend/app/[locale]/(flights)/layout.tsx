import type { Metadata } from "next";
import MobileNav from "@/src/layout/noon/MobileNav";
import LowerFooter from "@/src/layout/shared/lower-footer";
import Footer from "@/src/layout/noon/Footer";
import Header from "@/src/layout/noon/header/Header";

export const metadata: Metadata = {
  title: "Flights",
  description: "Flights & Travel Packages",
};

export default async function FlightsLayout({
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
