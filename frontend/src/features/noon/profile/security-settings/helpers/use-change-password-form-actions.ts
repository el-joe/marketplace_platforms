"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { ApiRequestError } from "@/src/lib/utils";
import { useAuthContext } from "@/src/providers/auth-provider";
import { changePassword } from "../api/security.actions";
import {
  getChangePasswordSchema,
  type ChangePasswordFormValues,
} from "./change-password.schema";

/** The change-password FORM only: form instance, submit, and the resulting status. */
export function useChangePasswordFormActions() {
  const t = useTranslations("profile");
  const { logout } = useAuthContext();
  const [isSaving, setIsSaving] = useState(false);
  const [isDone, setIsDone] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const form = useForm<ChangePasswordFormValues>({
    resolver: zodResolver(getChangePasswordSchema(t)),
    mode: "onChange",
  });

  const onSubmit = async (data: ChangePasswordFormValues) => {
    setIsSaving(true);
    setError(null);
    try {
      await changePassword(data);
      // Every session was revoked by the API; drop the local one too.
      logout();
      setIsDone(true);
    } catch (e) {
      if (e instanceof ApiRequestError && e.status === 422) {
        form.setError("current_password", {
          message: t("currentPasswordIncorrect"),
        });
      } else if (e instanceof ApiRequestError && e.status === 429) {
        setError(t("tooManyAttempts"));
      } else {
        setError(t("changePasswordFailed"));
      }
    } finally {
      setIsSaving(false);
    }
  };

  return { form, isSaving, isDone, error, onSubmit };
}
