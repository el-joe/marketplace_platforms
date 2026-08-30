import { fetchGlobalInstance } from "@/src/lib/utils";
import { Country } from "@/types";

/** Country-agnostic — GET /countries. */
export const getCountries = async () => {
  const { data: res } = await fetchGlobalInstance<{ data: Country[] }>(
    "/countries",
  );
  return res;
};
