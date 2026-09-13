"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchHelpTree } from "./fetchHelpTree";

export function useHelpTree() {
  return useQuery({
    queryKey: ["help-sheet", "tree"],
    queryFn: fetchHelpTree,
    staleTime: Infinity,
  });
}
