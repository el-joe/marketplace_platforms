import { fetchInstance } from "@/src/lib/utils";

import { ICategoryNavTree } from "../types";

export const getCategoriesTreeService = async () => {
  const { data: res } = await fetchInstance<{ data: ICategoryNavTree[] }>(
    "/categories",
  );
  return res;
};
