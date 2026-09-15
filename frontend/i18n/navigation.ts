"use client";
import { createNavigation } from "next-intl/navigation";
import { defineRouting } from "next-intl/routing";
import { apiBaseUrlGlobal } from "@/src/lib/utils";
import { Country } from "@/types";

// Module-scope config: must not depend on per-request state (cookies) since
// this module is evaluated once and shared across requests. The real,
// per-user default locale is resolved per-request in middleware.ts and
// i18n/request.ts (both call getRouting() inside a request-scoped function).
// Uses a plain, cookie-free fetch (not fetchGlobalInstance/fetchWithAuth,
// which reads cookies via a "use server" function — disallowed at module
// evaluation time) since the countries list needs no auth.
const countriesRes = await fetch(`${apiBaseUrlGlobal}/countries`, {
  cache: "no-store",
});
const { data: supportedCountriesData }: { data: Country[] } =
  await countriesRes.json();
const supportedCountries = supportedCountriesData.map((sc) => sc.site_code);

const routing = defineRouting({
  locales: supportedCountries.flatMap((r) => [`${r}-en`, `${r}-ar`]),
  localePrefix: "always",
  defaultLocale: "uae-en",
});

export const { Link, redirect, usePathname, useRouter, getPathname } =
  createNavigation(routing);
