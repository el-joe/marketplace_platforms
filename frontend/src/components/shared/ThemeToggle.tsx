"use client";
import React from "react";
import { useTheme } from "next-themes";

const ThemeToggle = () => {
  const { theme, setTheme } = useTheme();
  return (
    <button onClick={() => setTheme(theme === "dark" ? "light" : "dark")}>
      toggle color schema
    </button>
  );
};

export default ThemeToggle;
