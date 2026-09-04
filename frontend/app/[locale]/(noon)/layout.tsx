import type { Metadata } from "next";
// import Header from "@/src/layout/Header";
import MobileNav from "@/src/layout/noon/MobileNav";
import Header from "@/src/layout/noon/header/Header";
import LiveStreamButton from "@/src/components/shared/LiveStreamButton";
import EchoProvider from "@/src/providers/echo-provider";

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
    <EchoProvider>
      <Header />
      <main className="md:pt-26">{children}</main>
      <MobileNav />
      <LiveStreamButton />
    </EchoProvider>
  );
}
