import { fetchInstance } from "@/src/lib/utils";
import { IMarketersListResponse } from "./helpers/types";

export const getMarketersList = (params: URLSearchParams) =>
  fetchInstance<{ data: IMarketersListResponse }>(
    `/marketers?${params}`,
    undefined,
    true,
  );
