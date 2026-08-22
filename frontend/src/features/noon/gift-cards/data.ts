export type GiftCardCategory = {
  slug: string;
  labelKey: string;
  images: [string, string];
};

const placeholderCardImages: [string, string] = [
  "/images/congratulations_t1.avif",
  "/images/congratulations_t2.avif",
];

export const giftCardCategories: GiftCardCategory[] = [
  {
    slug: "any-occasion",
    labelKey: "categoryAnyOccasion",
    images: placeholderCardImages,
  },
  {
    slug: "eid-al-adha",
    labelKey: "categoryEidAlAdha",
    images: placeholderCardImages,
  },
  {
    slug: "noon-gift-card",
    labelKey: "categoryNoonGiftCard",
    images: placeholderCardImages,
  },
  {
    slug: "eid-mubarak",
    labelKey: "categoryEidMubarak",
    images: placeholderCardImages,
  },
  {
    slug: "congratulations",
    labelKey: "categoryCongratulations",
    images: placeholderCardImages,
  },
  {
    slug: "thank-you",
    labelKey: "categoryThankYou",
    images: placeholderCardImages,
  },
  {
    slug: "anniversary",
    labelKey: "categoryAnniversary",
    images: placeholderCardImages,
  },
];

/** Must match the API's denomination_cents enum (5000, 10000, 25000, 50000, 100000). */
export const giftCardAmounts = [50, 100, 250, 500, 1000] as const;

export const giftCardBrandLogos = [
  "noon",
  "SIVVI",
  "noon FOOD",
  "NAMSHI",
  "noon minutes",
] as const;

export function getGiftCardCategory(slug: string) {
  return giftCardCategories.find((category) => category.slug === slug);
}
