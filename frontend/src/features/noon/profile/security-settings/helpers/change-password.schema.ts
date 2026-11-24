import { z } from "zod";

/** Mirrors the API rule (POST /security/change-password): current password + new (min 8) + confirmation. */
export function getChangePasswordSchema(t: (key: string) => string) {
  return z
    .object({
      current_password: z.string().min(1, t("currentPasswordRequired")),
      new_password: z.string().min(8, t("newPasswordTooShort")),
      new_password_confirmation: z.string().min(1, t("confirmPasswordRequired")),
    })
    .refine((v) => v.new_password === v.new_password_confirmation, {
      message: t("passwordsDontMatch"),
      path: ["new_password_confirmation"],
    })
    .refine((v) => v.new_password !== v.current_password, {
      message: t("newPasswordSameAsCurrent"),
      path: ["new_password"],
    });
}

export type ChangePasswordFormValues = z.infer<
  ReturnType<typeof getChangePasswordSchema>
>;
