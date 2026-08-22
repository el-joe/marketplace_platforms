import { paramsServer } from "./paramsServer";

const FILTER_PREFIX = process.env.NEXT_PUBLIC_FILTER_PREFIX ?? "filter";

export type ApiFilter = {
  targetEndpoint: string;
  filters: Record<string, string>;
};

export async function resolveApiFilters(): Promise<ApiFilter[]> {
  let searchParams: URLSearchParams;

  if (typeof window === "undefined") {
    const headerParams = await paramsServer();
    searchParams = new URLSearchParams(headerParams);
  } else {
    searchParams = new URLSearchParams(window.location.search);
  }

  const endpointFilters = new Map<string, Record<string, string>>();

  for (const [key, value] of searchParams.entries()) {
    if (!key.startsWith(`${FILTER_PREFIX}_`)) continue;

    const segments = key.replace(`${FILTER_PREFIX}_`, "").split("_");

    if (segments.length < 2) continue;

    const [targetEndpoint, ...filterParts] = segments;
    const filterName = filterParts.join("_");

    endpointFilters.set(targetEndpoint, {
      ...(endpointFilters.get(targetEndpoint) ?? {}),
      [filterName]: value,
    });
  }

  return Array.from(endpointFilters.entries()).map(
    ([targetEndpoint, filters]) => ({
      targetEndpoint,
      filters,
    }),
  );
}
