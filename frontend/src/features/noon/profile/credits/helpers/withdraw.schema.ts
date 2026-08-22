import { z } from "zod";

export function getWithdrawSchema(t: (key: string) => string) {
  return z.object({
    amount: z
      .string()
      .trim()
      .min(1, t("invalidWithdrawalAmount"))
      .refine((value) => Number(value) > 0, t("invalidWithdrawalAmount")),
    bankName: z.string().trim().min(1, t("invalidBankName")),
    bankIban: z.string().trim().min(1, t("invalidBankIban")),
  });
}

export type WithdrawFormValues = z.infer<ReturnType<typeof getWithdrawSchema>>;
