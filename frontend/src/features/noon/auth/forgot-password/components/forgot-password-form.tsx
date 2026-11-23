"use client";
import { FormProvider } from "react-hook-form";
import { useTranslations } from "next-intl";
import { ArrowLeftIcon, MailCheckIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { Spinner } from "@/src/components/ui/spinner";
import FormInput from "@/src/components/ui/form-inputs/form-input";
import { useFormActions } from "../helpers/use-form-actions";

type Props = { onBack: () => void };

/** Request a reset link by email; then a success state that never says whether the account exists. */
export default function ForgotPasswordForm({ onBack }: Props) {
  const t = useTranslations("authDialog.forgotPassword");
  const { form, isSending, sentTo, error, onSubmit } = useFormActions();
  const {
    handleSubmit,
    formState: { isValid },
  } = form;

  const backButton = (
    <Button
      type="button"
      variant="ghost"
      className="text-blue-2 h-10 self-center"
      onClick={onBack}
    >
      <ArrowLeftIcon className="size-4 rtl:-scale-x-100" />
      {t("backToLogin")}
    </Button>
  );

  if (sentTo) {
    return (
      <div
        className="flex flex-col gap-3 items-center text-center w-full sm:w-8/12 mx-auto py-2"
        role="status"
        aria-live="polite"
      >
        <MailCheckIcon className="size-10 text-green-600" aria-hidden />
        <h3 className="text-lg font-bold">{t("sentTitle")}</h3>
        <p className="text-secondary">
          {t("sentMessage", { email: sentTo })}
        </p>
        <p className="text-secondary text-sm">{t("sentHint")}</p>
        {backButton}
      </div>
    );
  }

  return (
    <FormProvider {...form}>
      <form
        onSubmit={handleSubmit(onSubmit)}
        className="flex flex-col gap-2 items-stretch w-full sm:w-8/12 mx-auto"
        noValidate
      >
        <h3 className="text-lg font-bold text-center">{t("title")}</h3>
        <p className="text-secondary text-center mb-1">{t("description")}</p>
        <FormInput
          name="email"
          type="email"
          inputMode="email"
          autoComplete="email"
          className="h-12 w-full"
          placeholder={t("emailPlaceholder")}
          aria-label={t("emailPlaceholder")}
          autoFocus
        />
        <Button
          className="h-12 bg-gray-2 w-full cursor-pointer"
          disabled={!isValid || isSending}
          type="submit"
        >
          {t("submit")}
          {isSending && <Spinner />}
        </Button>
        {error && (
          <p className="text-xs text-destructive mt-1.5" role="alert">
            {error}
          </p>
        )}
        {backButton}
      </form>
    </FormProvider>
  );
}
