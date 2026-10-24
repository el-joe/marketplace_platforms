import { getLocale } from "next-intl/server";
import resolveCookie from "@/src/helpers/resolveCookie";
import { apiPublicBaseUrlGlobal } from "@/src/lib/utils";
import { GENDER_PARAM_TO_API, GRID_PER_PAGE } from "./helpers/constants";
import { isMarketersMockEnabled, mockGrouped, mockPage } from "./helpers/mock-data";
import { normalizeGrouped, normalizePage } from "./helpers/normalize";
import type {
  GenderParam,
  GroupedMarketers,
  MarketersPage,
  MarketerType,
} from "./helpers/types";

async function request(query: URLSearchParams) {
  const [country, locale] = await Promise.all([resolveCookie("country"), getLocale()]);
  const res = await fetch(`${apiPublicBaseUrlGlobal}/${country}/marketers?${query.toString()}`, {
    cache: "no-store",
    headers: { Accept: "application/json", "Accept-Language": locale.split("-").pop() ?? "en" },
  });
  if (!res.ok) throw new Error(`Marketers API responded ${res.status}`);
  const body = await res.json();
  return body.data;
}

/** Landing view: sections per type + hero/strip banners for the gender (`grouped=1`). */
export async function getGroupedMarketers(gender: GenderParam): Promise<GroupedMarketers> {
  if (isMarketersMockEnabled()) return mockGrouped(gender);
  const query = new URLSearchParams({ gender: GENDER_PARAM_TO_API[gender], grouped: "1" });
  return normalizeGrouped(await request(query));
}

/** "View all" view: paginated grid of one type for the gender. */
export async function getMarketersByType(params: {
  gender: GenderParam;
  type: MarketerType;
  page: number;
}): Promise<MarketersPage> {
  if (isMarketersMockEnabled()) return mockPage(params.gender, params.type, params.page);
  const query = new URLSearchParams({
    gender: GENDER_PARAM_TO_API[params.gender],
    type: params.type,
    page: String(params.page),
    per_page: String(GRID_PER_PAGE),
  });
  return normalizePage(await request(query));
}
