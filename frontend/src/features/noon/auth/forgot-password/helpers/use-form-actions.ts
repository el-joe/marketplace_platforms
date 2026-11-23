"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import { ApiRequestError } from "@/src/lib/utils";
import { forgotPasswordService } from "@/src/services/auth";
import {
  getForgotPasswordSchema,
  type ForgotPasswordFormValues,
} from "./forgot-password.schema";

export function useFormActions() {
  const t = useTranslations("authDialog");
  const locale = useLocale();
  const [isSending, setIsSending] = useState(false);
  const [sentTo, setSentTo] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const form = useForm<ForgotPasswordFormValues>({
    resolver: zodResolver(getForgotPasswordSchema(t)),
    mode: "onChange",
  });

  const onSubmit = async ({ email }: ForgotPasswordFormValues) => {
    setIsSending(true);
    setError(null);
    try {
      await forgotPasswordService({ email_or_phone: email, locale });
      setSentTo(email);
    } catch (e) {
      setError(
        e instanceof ApiRequestError && e.status === 429
          ? t("forgotPassword.tooManyRequests")
          : t("forgotPassword.genericError"),
      );
    } finally {
      setIsSending(false);
    }
  };

  return { form, isSending, sentTo, error, onSubmit };
}
