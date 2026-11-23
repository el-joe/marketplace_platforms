import { z } from "zod";

/** Mirrors the API rule: min 8 characters + confirmation. */
export function getResetPasswordSchema(t: (key: string) => string) {
  return z
    .object({
      password: z.string().min(8, t("passwordTooShort")),
      password_confirmation: z.string().min(1, t("confirmRequired")),
    })
    .refine((v) => v.password === v.password_confirmation, {
      message: t("passwordsDontMatch"),
      path: ["password_confirmation"],
    });
}

export type ResetPasswordFormValues = z.infer<
  ReturnType<typeof getResetPasswordSchema>
>;
