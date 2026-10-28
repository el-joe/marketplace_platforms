import { fetchInstance } from "@/src/lib/utils";
import {
  IMarketersBannersResponse,
  IMarketersListResponse,
} from "./helpers/types";

export const getMarketersList = (params: URLSearchParams) =>
  fetchInstance<{ data: IMarketersListResponse }>(
    `/marketers?${params}`,
    undefined,
    true,
  );

export const getMarketersBanners = (gender: string, locale: string) =>
  fetchInstance<{ data: IMarketersBannersResponse }>(
    `/marketers?${new URLSearchParams({ banners_only: "1", gender, locale })}`,
    undefined,
    true,
  );
