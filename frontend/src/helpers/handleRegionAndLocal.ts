export function getLanguage(locale: string) {
  return locale.split("-")[1];
}

export function getRegion(locale: string) {
  return locale.split("-")[0];
}

export function margeRegionAndLocal(
  region: string,
  locales: string[],
): string[] {
  return locales.map((locale) => `${region}-${locale}`);
}
