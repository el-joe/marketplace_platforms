import { z } from "zod";

type T = (key: string) => string;

export const getSpecialRequestSchema = (t: T) =>
  z.object({
    category_id: z.string().min(1, t("categoryRequired")),
    city_id: z.string(),
    title_en: z.string().trim().min(3, t("titleRequired")).max(255),
    description_en: z.string().trim().min(10, t("descriptionTooShort")),
    budget: z.string().regex(/^\d*$/, t("budgetInvalid")),
    budget_currency: z.string(),
  });

export type SpecialRequestFormValues = z.infer<
  ReturnType<typeof getSpecialRequestSchema>
>;
