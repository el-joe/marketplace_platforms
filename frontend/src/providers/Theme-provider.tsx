"use client";
import React from "react";
// import { ThemeProvider as NextThemeProvider } from "next-themes";
type Props = {
  children: React.ReactNode;
};

const ThemeProvider = ({ children }: Props) => {
  return (
    <>{children}</>
    // <NextThemeProvider
    //   attribute={"class"}
    //   enableSystem
    //   defaultTheme="system"
    //   disableTransitionOnChange
    // >
    //   {children}
    // </NextThemeProvider>
  );
};

export default ThemeProvider;
