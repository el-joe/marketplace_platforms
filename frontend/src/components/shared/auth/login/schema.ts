import { z } from "zod";

export function getLoginSchema(t: (key: string) => string) {
  return z.object({
    email_or_phone: z.string().trim().min(8, t("enterEmailOrMobileError")),
    password: z.string().trim().min(6, t("enterPasswordError")),
  });
}

export type loginFormValues = z.infer<ReturnType<typeof getLoginSchema>>;
