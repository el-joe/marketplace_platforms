export type SizeGuideUnit = "cm" | "in";

export type LocalizedText = { en: string | null; ar: string | null };

export interface SizeGuideColumn {
  key: string;
  label: { en: string; ar: string };
  /** Measurement columns are converted when the customer switches cm/in. */
  is_measurement: boolean;
}

export interface SizeGuideRow {
  size: string;
  values: Record<string, string>;
}

/** Vendor size guide ("size map") as returned by the customer API. */
export interface SizeGuide {
  id: string;
  title: { en: string; ar: string };
  intro: LocalizedText;
  how_to_measure: LocalizedText;
  unit: SizeGuideUnit;
  image_url: string | null;
  columns: SizeGuideColumn[];
  rows: SizeGuideRow[];
  vendor?: { id: string; store_name: string; store_slug: string } | null;
}
