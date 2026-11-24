"use client";

import { FormProvider } from "react-hook-form";
import { useTranslations } from "next-intl";
import { CircleCheckIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { Spinner } from "@/src/components/ui/spinner";
import FormInput from "@/src/components/ui/form-inputs/form-input";
import { useAuthContext } from "@/src/providers/auth-provider";
import { useChangePasswordFormActions } from "../../helpers/use-change-password-form-actions";

export default function ChangePasswordForm() {
  const t = useTranslations("profile");
  const { setAuthDialogIsOpen } = useAuthContext();
  const { form, isSaving, isDone, error, onSubmit } =
    useChangePasswordFormActions();
  const {
    handleSubmit,
    formState: { isValid },
  } = form;

  if (isDone) {
    return (
      <div
        className="flex flex-col items-center text-center gap-3 py-6"
        role="status"
        aria-live="polite"
      >
        <CircleCheckIcon className="size-10 text-green-600" aria-hidden />
        <h2 className="text-lg font-bold">{t("passwordChangedTitle")}</h2>
        <p className="text-secondary">{t("passwordChangedMessage")}</p>
        <Button
          className="h-12 bg-gray-2 w-full max-w-sm"
          onClick={() => setAuthDialogIsOpen(true)}
        >
          {t("signInAgain")}
        </Button>
      </div>
    );
  }

  return (
    <FormProvider {...form}>
      <form
        onSubmit={handleSubmit(onSubmit)}
        className="flex flex-col gap-4 max-w-md"
        noValidate
        data-testid="change-password-form"
      >
        <label className="flex flex-col gap-1 text-sm font-semibold">
          {t("currentPassword")}
          <FormInput
            name="current_password"
            type="password"
            autoComplete="current-password"
            className="h-12 w-full font-normal"
            autoFocus
          />
        </label>
        <label className="flex flex-col gap-1 text-sm font-semibold">
          {t("newPassword")}
          <FormInput
            name="new_password"
            type="password"
            autoComplete="new-password"
            className="h-12 w-full font-normal"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm font-semibold">
          {t("confirmNewPassword")}
          <FormInput
            name="new_password_confirmation"
            type="password"
            autoComplete="new-password"
            className="h-12 w-full font-normal"
          />
        </label>
        <p className="text-xs text-secondary">{t("changePasswordHint")}</p>
        <Button
          className="h-12 bg-gray-2 w-full cursor-pointer"
          disabled={!isValid || isSaving}
          type="submit"
        >
          {t("changePasswordSubmit")}
          {isSaving && <Spinner />}
        </Button>
        {error && (
          <p className="text-xs text-destructive" role="alert">
            {error}
          </p>
        )}
      </form>
    </FormProvider>
  );
}
