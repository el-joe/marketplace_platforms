"use client";

import { useNavigationLoadingContext } from "@/src/providers/navigation-loading-provider";

const NavigationLoadingOverlay = () => {
  const { isLoading } = useNavigationLoadingContext();

  return (
    <div
      aria-hidden
      className={`fixed inset-0 z-50 bg-white/60 backdrop-blur-[1px] transition-opacity duration-200 ${
        isLoading
          ? "pointer-events-auto opacity-100"
          : "pointer-events-none opacity-0"
      }`}
    />
  );
};

export default NavigationLoadingOverlay;
