import { fetchInstance } from "@/src/lib/utils";
import { mapHelpCenterTreeToHelpNode } from "../helpers/map-help-tree";
import type { HelpCenterCategoryDto } from "./help-center-tree.types";

/**
 * Fetches the nested help-center category/article tree from
 * `GET {country}/help-center/tree` and maps it into the `HelpNode` shape the
 * help-sheet UI renders. Replaces the previous static `mockData.ts` tree —
 * see enhancement.md P-26.
 */
export function fetchHelpTree(locale: "ar" | "en", rootTitle: string) {
  return fetchInstance<{ data: HelpCenterCategoryDto[] }>(
    "/help-center/tree",
  ).then(({ data }) => mapHelpCenterTreeToHelpNode(data, locale, rootTitle));
}
