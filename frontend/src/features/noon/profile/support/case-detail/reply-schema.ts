import { z } from "zod";

export function getReplySchema(t: (key: string) => string) {
  return z.object({
    message: z.string().trim().min(1, t("replyRequired")),
  });
}

export type ReplyFormValues = z.infer<ReturnType<typeof getReplySchema>>;
