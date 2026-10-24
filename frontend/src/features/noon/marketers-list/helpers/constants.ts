import type { GenderParam, MarketerGender, MarketerType } from "./types";

export const GRID_PER_PAGE = 24;

export const GENDER_TABS: { value: GenderParam; labelKey: "tabMen" | "tabWomen" }[] = [
  { value: "men", labelKey: "tabMen" },
  { value: "women", labelKey: "tabWomen" },
];

export const GENDER_PARAM_TO_API: Record<GenderParam, MarketerGender> = {
  men: "male",
  women: "female",
};

export const MARKETER_TYPES: MarketerType[] = ["influencer", "affiliate", "broker"];

export const TYPE_SECTION_KEYS = {
  influencer: "sectionInfluencers",
  affiliate: "sectionAffiliates",
  broker: "sectionBrokers",
} as const;

export const TYPE_BADGE_KEYS = {
  influencer: "typeInfluencer",
  affiliate: "typeAffiliate",
  broker: "typeBroker",
} as const;

/** Gold accent shared by the "view all" button and active tab (boutiqaat-style). */
export const GOLD_TEXT = "text-[#a8791a]";
export const GOLD_BORDER = "border-[#c9a24a]";
export const GOLD_BG = "bg-[#c9a24a]";

/** Swiper breakpoints for carousel rows (portrait cards). */
export const CAROUSEL_BREAKPOINTS = {
  0: { slidesPerView: 2.2, spaceBetween: 12 },
  640: { slidesPerView: 3.2, spaceBetween: 14 },
  768: { slidesPerView: 4, spaceBetween: 16 },
  1024: { slidesPerView: 5, spaceBetween: 16 },
  1280: { slidesPerView: 6, spaceBetween: 16 },
} as const;
