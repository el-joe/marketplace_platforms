"use client";

import { useQuery } from "@tanstack/react-query";
import useLocale from "@/src/hooks/use-locale";
import { fetchHelpTree } from "./fetchHelpTree";

const ROOT_TITLE = "How can we help?";

export function useHelpTree() {
  const locale = useLocale();

  return useQuery({
    queryKey: ["help-sheet", "tree", locale],
    queryFn: () => fetchHelpTree(locale, ROOT_TITLE),
    staleTime: Infinity,
  });
}
