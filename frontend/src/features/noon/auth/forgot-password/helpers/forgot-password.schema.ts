import { z } from "zod";

export function getForgotPasswordSchema(t: (key: string) => string) {
  return z.object({
    email: z.string().trim().email(t("forgotPassword.invalidEmail")),
  });
}

export type ForgotPasswordFormValues = z.infer<
  ReturnType<typeof getForgotPasswordSchema>
>;
