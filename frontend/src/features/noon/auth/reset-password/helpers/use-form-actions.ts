"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { ApiRequestError } from "@/src/lib/utils";
import { resetPasswordService } from "@/src/services/auth";
import {
  getResetPasswordSchema,
  type ResetPasswordFormValues,
} from "./reset-password.schema";

export type ResetStatus = "idle" | "done" | "invalidLink";

export function useFormActions(token: string, email: string) {
  const t = useTranslations("resetPassword");
  const [isSaving, setIsSaving] = useState(false);
  const [status, setStatus] = useState<ResetStatus>("idle");
  const [error, setError] = useState<string | null>(null);

  const form = useForm<ResetPasswordFormValues>({
    resolver: zodResolver(getResetPasswordSchema(t)),
    mode: "onChange",
  });

  const onSubmit = async (data: ResetPasswordFormValues) => {
    setIsSaving(true);
    setError(null);
    try {
      await resetPasswordService({ token, email, ...data });
      setStatus("done");
    } catch (e) {
      if (e instanceof ApiRequestError && e.status === 422) {
        setStatus("invalidLink");
      } else if (e instanceof ApiRequestError && e.status === 429) {
        setError(t("tooManyRequests"));
      } else {
        setError(t("genericError"));
      }
    } finally {
      setIsSaving(false);
    }
  };

  return { form, isSaving, status, error, onSubmit };
}
