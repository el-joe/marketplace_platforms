import { z } from "zod";

export function getRegisterSchema(
  t: (key: string, optional?: Record<string, string>) => string,
) {
  return z
    .object({
      name: z
        .string()
        .trim()
        .min(3, t("nameMinLengthErr", { value: "3" }))
        .max(255, t("nameMaxLengthErr", { value: "255" })),
      email: z.email(t("emailInvalidErr")).max(120),
      phone: z
        .string()
        .max(20, t("phoneMaxLengthErr", { value: "20" }))
        .optional(),
      password: z
        .string()
        .trim()
        .min(8, t("passwordMinLengthErr", { value: "8" })),
      password_confirmation: z
        .string()
        .trim()
        .min(8, t("passwordMinLengthErr", { value: "8" })),
      marketingCommunicationsConfirm: z.boolean().refine((val) => val, {
        message: t("marketingCommunicationConfirmErr"),
      }),
    })
    .refine((data) => data.password === data.password_confirmation, {
      message: t("passwordDontMatchErr"),
      path: ["password_confirmation"],
    });
}

export type registerFormValues = z.infer<ReturnType<typeof getRegisterSchema>>;
