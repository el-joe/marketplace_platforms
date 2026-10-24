import { MARKETER_TYPES } from "./constants";
import type {
  GroupedMarketers,
  MarketerBanner,
  MarketerCardData,
  MarketerSection,
  MarketersPage,
} from "./types";

/* eslint-disable @typescript-eslint/no-explicit-any */

export function normalizeCard(raw: any): MarketerCardData {
  return {
    id: String(raw.id),
    slug: String(raw.slug ?? raw.profile_slug ?? ""),
    name: String(raw.name ?? ""),
    avatar_url: raw.avatar_url ?? null,
    banner_url: raw.banner_url ?? null,
    type: raw.type ?? raw.marketer_type ?? "influencer",
    type_label: raw.type_label ?? null,
    gender: raw.gender ?? null,
    specialty: raw.specialty ?? null,
  };
}

export function normalizeBanner(raw: any): MarketerBanner {
  return {
    id: String(raw.id),
    title: raw.title ?? null,
    subtitle: raw.subtitle ?? null,
    cta_label: raw.cta_label ?? null,
    link: raw.link ?? null,
    image_desktop: raw.image_desktop ?? raw.image_mobile ?? null,
    image_mobile: raw.image_mobile ?? raw.image_desktop ?? null,
    priority: Number(raw.priority ?? 0),
    click_url: raw.click_url ?? null,
  };
}

export function normalizePage(data: any): MarketersPage {
  const items = (data?.items ?? []).map(normalizeCard);
  return {
    items,
    meta: {
      current_page: data?.meta?.current_page ?? 1,
      last_page: data?.meta?.last_page ?? 1,
      per_page: data?.meta?.per_page ?? items.length,
      total: data?.meta?.total ?? items.length,
    },
  };
}

/** Normalizes `grouped=1`; if the backend answered with a flat list, groups it by type locally. */
export function normalizeGrouped(data: any): GroupedMarketers {
  let sections: MarketerSection[];
  if (Array.isArray(data?.sections)) {
    sections = data.sections.map((s: any) => ({
      type: s.type,
      label: s.label ?? null,
      total: Number(s.total ?? s.items?.length ?? 0),
      items: (s.items ?? []).map(normalizeCard),
    }));
  } else {
    const cards: MarketerCardData[] = (data?.items ?? []).map(normalizeCard);
    sections = MARKETER_TYPES.map((type) => {
      const items = cards.filter((c) => c.type === type);
      return { type, label: null, total: items.length, items: items.slice(0, 12) };
    });
  }
  return {
    sections: sections.filter((s) => s.items.length > 0),
    banners: {
      hero: (data?.banners?.hero ?? []).map(normalizeBanner),
      strip: (data?.banners?.strip ?? []).map(normalizeBanner),
    },
  };
}
