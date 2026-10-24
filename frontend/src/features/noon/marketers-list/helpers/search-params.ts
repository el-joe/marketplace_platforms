import { MARKETER_TYPES } from "./constants";
import type { GenderParam, ListSearchParams, MarketerType } from "./types";

type Raw = { gender?: string; type?: string; page?: string };

/** Parses the raw `?gender=&type=&page=` query into safe, defaulted values (Men default). */
export function parseListSearchParams(raw: Raw): ListSearchParams {
  const gender: GenderParam = raw.gender === "women" ? "women" : "men";
  const type = MARKETER_TYPES.find((t) => t === raw.type) as MarketerType | undefined;
  const page = Math.max(1, Number.parseInt(raw.page ?? "1", 10) || 1);
  return { gender, type, page };
}

/** Builds a `/marketers` href preserving only the given params. */
export function buildListHref(params: { gender: GenderParam; type?: string; page?: number }): string {
  const query = new URLSearchParams({ gender: params.gender });
  if (params.type) query.set("type", params.type);
  if (params.page && params.page > 1) query.set("page", String(params.page));
  return `/marketers?${query.toString()}`;
}
