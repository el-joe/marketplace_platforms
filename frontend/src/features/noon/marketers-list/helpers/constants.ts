export interface MarketerTabConfig {
  value?: string;
  labelKey: "tabAll" | "tabInfluencers" | "tabAffiliates";
}

export const MARKETER_TABS: MarketerTabConfig[] = [
  { value: undefined, labelKey: "tabAll" },
  { value: "influencer", labelKey: "tabInfluencers" },
  { value: "affiliate", labelKey: "tabAffiliates" },
];

/** Rotating banner-fallback gradients so a grid of cards without a banner_url isn't monotone. */
export const CARD_GRADIENTS: string[] = [
  "from-amber-300 via-[var(--main-color)] to-yellow-200",
  "from-fuchsia-400 via-purple-400 to-indigo-300",
  "from-sky-400 via-cyan-300 to-teal-200",
  "from-rose-400 via-pink-400 to-amber-200",
  "from-emerald-400 via-teal-300 to-lime-200",
  "from-orange-400 via-amber-300 to-yellow-200",
];

/** Hover-shadow tint paired 1:1 with CARD_GRADIENTS, so each card's glow matches its own palette. */
export const CARD_GLOWS: string[] = [
  "rgba(245,158,11,0.35)",
  "rgba(192,38,211,0.3)",
  "rgba(14,165,233,0.3)",
  "rgba(244,63,94,0.3)",
  "rgba(16,185,129,0.3)",
  "rgba(249,115,22,0.3)",
];
