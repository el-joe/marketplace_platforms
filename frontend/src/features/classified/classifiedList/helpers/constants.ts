/** Storefront surfaces a classified section can be placed on by the admin (mirrors the backend ClassifiedPlacement enum). */
export const CLASSIFIED_PLACEMENT = {
  nav: "nav",
  landing: "landing",
  home: "home",
} as const;

/** Sections created before placements existed keep showing on the landing page. */
export const LEGACY_PLACEMENTS: string[] = [
  CLASSIFIED_PLACEMENT.nav,
  CLASSIFIED_PLACEMENT.landing,
];
