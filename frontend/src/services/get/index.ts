import { fetchGlobalInstance, fetchInstance } from "@/src/lib/utils";
import { CategoryNavTree, Country } from "@/types";

export const getCategoriesTree = async () => {
  const { data: res } = await fetchInstance<{ data: CategoryNavTree[] }>(
    "/categories",
  );
  return res;
};

/** Country-agnostic — GET /countries. */
export const getCountries = async () => {
  const { data: res } = await fetchGlobalInstance<{ data: Country[] }>(
    "/countries",
  );
  return res;
};
