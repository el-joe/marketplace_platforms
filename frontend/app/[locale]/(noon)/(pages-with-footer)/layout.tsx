import React from "react";
import PopularSearchesTags from "@/src/layout/noon/PopularSearchesTags";
import HelpSection from "@/src/layout/noon/HelpSection";
import Footer from "@/src/layout/noon/Footer";

const MainNoonlayout = ({ children }: { children: React.ReactNode }) => {
  return (
    <>
      {children}
      <PopularSearchesTags />
      <HelpSection />
      <Footer />
    </>
  );
};

export default MainNoonlayout;
