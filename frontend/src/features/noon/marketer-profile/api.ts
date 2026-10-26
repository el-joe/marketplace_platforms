import { fetchInstance } from "@/src/lib/utils";
import { IMarketer } from "./helpers/types";

export const getMarketerProfile = (slug: string, params: URLSearchParams) =>
  fetchInstance<{ data: IMarketer }>(
    `/marketers/${slug}?${params}`,
    undefined,
    true,
  );
