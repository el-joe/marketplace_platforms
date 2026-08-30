import { fetchInstance } from "@/src/lib/utils";

import {
  ICategoryNavTree,
  ISearchResponse,
  ISearchSuggestionsResponse,
} from "../types";

export const getSearchSuggestionsService = (
  query: string,
  signal?: AbortSignal,
) => {
  return fetchInstance<ISearchSuggestionsResponse>(
    `/search/suggestions?q=${encodeURIComponent(query)}`,
    {
      method: "GET",
      signal,
    },
  );
};

export const searchService = (query: string) =>
  fetchInstance<ISearchResponse>(`/search?q=${query}`);

export const getCategoriesTreeService = async () => {
  const { data: res } = await fetchInstance<{ data: ICategoryNavTree[] }>(
    "/categories",
  );
  return res;
};
