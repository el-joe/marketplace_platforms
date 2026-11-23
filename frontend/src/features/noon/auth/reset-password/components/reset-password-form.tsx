"use client";
import { FormProvider } from "react-hook-form";
import { useTranslations } from "next-intl";
import { CircleCheckIcon, TriangleAlertIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { Spinner } from "@/src/components/ui/spinner";
import FormInput from "@/src/components/ui/form-inputs/form-input";
import { useAuthContext } from "@/src/providers/auth-provider";
import { useFormActions } from "../helpers/use-form-actions";

type Props = { token: string; email: string };

export default function ResetPasswordForm({ token, email }: Props) {
  const t = useTranslations("resetPassword");
  const { setAuthDialogIsOpen } = useAuthContext();
  const { form, isSaving, status, error, onSubmit } = useFormActions(
    token,
    email,
  );
  const {
    handleSubmit,
    formState: { isValid },
  } = form;

  if (!token || !email || status === "invalidLink") {
    return (
      <div className="flex flex-col items-center text-center gap-3" role="alert">
        <TriangleAlertIcon className="size-10 text-amber-500" aria-hidden />
        <h2 className="text-lg font-bold">{t("invalidTitle")}</h2>
        <p className="text-secondary">{t("invalidMessage")}</p>
        <Button
          className="h-12 bg-gray-2 w-full"
          onClick={() => setAuthDialogIsOpen(true)}
        >
          {t("requestNewLink")}
        </Button>
      </div>
    );
  }

  if (status === "done") {
    return (
      <div
        className="flex flex-col items-center text-center gap-3"
        role="status"
        aria-live="polite"
      >
        <CircleCheckIcon className="size-10 text-green-600" aria-hidden />
        <h2 className="text-lg font-bold">{t("successTitle")}</h2>
        <p className="text-secondary">{t("successMessage")}</p>
        <Button
          className="h-12 bg-gray-2 w-full"
          onClick={() => setAuthDialogIsOpen(true)}
        >
          {t("login")}
        </Button>
      </div>
    );
  }

  return (
    <FormProvider {...form}>
      <form
        onSubmit={handleSubmit(onSubmit)}
        className="flex flex-col gap-3"
        noValidate
      >
        <p className="text-secondary text-sm">
          {t("forAccount")}{" "}
          <span className="font-semibold text-primary break-all" dir="ltr">
            {email}
          </span>
        </p>
        <label className="flex flex-col gap-1 text-sm font-semibold">
          {t("newPassword")}
          <FormInput
            name="password"
            type="password"
            autoComplete="new-password"
            className="h-12 w-full font-normal"
            autoFocus
          />
        </label>
        <label className="flex flex-col gap-1 text-sm font-semibold">
          {t("confirmPassword")}
          <FormInput
            name="password_confirmation"
            type="password"
            autoComplete="new-password"
            className="h-12 w-full font-normal"
          />
        </label>
        <p className="text-xs text-secondary">{t("passwordHint")}</p>
        <Button
          className="h-12 bg-gray-2 w-full cursor-pointer"
          disabled={!isValid || isSaving}
          type="submit"
        >
          {t("submit")}
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
