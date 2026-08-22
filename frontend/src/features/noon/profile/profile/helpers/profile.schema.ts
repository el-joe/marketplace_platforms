import { z } from "zod";

export function getProfileSchema(t: (key: string) => string) {
  return z.object({
    email: z.string(),
    phone: z.string(),
    firstName: z.string().trim().min(1, t("invalidFirstName")),
    lastName: z.string().trim().min(1, t("invalidLastName")),
    date_of_birth: z.string().optional(),
    gender: z.enum(["male", "female"]).optional(),
    nationality: z.string().optional(),
  });
}

export type ProfileFormValues = z.infer<ReturnType<typeof getProfileSchema>>;
