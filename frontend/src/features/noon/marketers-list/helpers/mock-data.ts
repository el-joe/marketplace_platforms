import "server-only";
import { GRID_PER_PAGE, MARKETER_TYPES } from "./constants";
import type {
  GenderParam,
  GroupedMarketers,
  MarketerBanner,
  MarketerCardData,
  MarketersPage,
  MarketerType,
} from "./types";

/**
 * Dev-only mock of the WP-B contract. Active only when MARKETERS_LIST_MOCK=1
 * and NODE_ENV !== "production" (see api.ts). Never used in production builds.
 */
export const isMarketersMockEnabled = (): boolean =>
  process.env.MARKETERS_LIST_MOCK === "1" && process.env.NODE_ENV !== "production";

const NAMES = {
  male: ["Ahmed Hassan", "Omar Khaled", "Youssef Adel", "Karim Nabil", "Tarek Samy", "Mostafa Reda", "Hany Fawzy", "Sherif Magdy", "Adam Wael", "Ziad Amr", "Bassem Lotfy", "Hossam Ezz", "Walid Said", "Nour Eldin"],
  female: ["Sara Mahmoud", "Mariam Ali", "Nada Samir", "Hana Tarek", "Layla Hossam", "Dina Wael", "Rana Khaled", "Salma Adel", "Yasmin Reda", "Farah Amr", "Aya Nabil", "Mai Fawzy", "Nour Hany", "Rawan Said"],
} as const;

const ALL: MarketerCardData[] = (["male", "female"] as const).flatMap((gender) =>
  NAMES[gender].map((name, i) => {
    const type: MarketerType = MARKETER_TYPES[i % 3];
    return {
      id: `mock-${gender}-${i}`,
      slug: `mock-${gender}-${i}`,
      name,
      avatar_url: i % 4 === 3 ? null : "/images/no-image-available-icon.jpg",
      banner_url: null,
      type,
      type_label: null,
      gender,
      specialty: null,
    } satisfies MarketerCardData;
  }),
);

const banner = (id: string, title: string): MarketerBanner => ({
  id,
  title,
  subtitle: "Mock banner",
  cta_label: "Shop now",
  link: "/marketers",
  image_desktop: null,
  image_mobile: null,
  priority: 0,
  click_url: null,
});

const apiGender = (g: GenderParam) => (g === "men" ? "male" : "female");

export function mockGrouped(gender: GenderParam): GroupedMarketers {
  const pool = ALL.filter((m) => m.gender === apiGender(gender));
  return {
    sections: MARKETER_TYPES.map((type) => {
      const items = pool.filter((m) => m.type === type);
      return { type, label: null, total: items.length, items };
    }),
    banners: {
      hero: [banner("h1", "Mock hero one"), banner("h2", "Mock hero two")],
      strip: [banner("s1", "Mock strip A"), banner("s2", "Mock strip B")],
    },
  };
}

export function mockPage(gender: GenderParam, type: MarketerType, page: number): MarketersPage {
  const pool = ALL.filter((m) => m.gender === apiGender(gender) && m.type === type);
  const perPage = Math.min(GRID_PER_PAGE, 4); // small page size so pagination is testable
  const items = pool.slice((page - 1) * perPage, page * perPage);
  return {
    items,
    meta: {
      current_page: page,
      last_page: Math.max(1, Math.ceil(pool.length / perPage)),
      per_page: perPage,
      total: pool.length,
    },
  };
}
