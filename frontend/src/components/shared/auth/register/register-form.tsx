"use client";
import { Button } from "../../../ui/button";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import FormInput from "../../../ui/form-inputs/form-input";
import { FormProvider, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useAuthContext } from "@/src/providers/auth-provider";
import { Spinner } from "@/src/components/ui/spinner";
import FormCheckbox from "@/src/components/ui/form-inputs/form-checkbox";
import { getRegisterSchema, registerFormValues } from "./schema";

export default function RegisterForm() {
  const t = useTranslations("authDialog");
  const { register, isRegistering, registerError, registerIsError } =
    useAuthContext();

  const form = useForm<registerFormValues>({
    resolver: zodResolver(getRegisterSchema(t)),
  });

  const {
    handleSubmit,
    formState: { isDirty, isValid },
  } = form;
  const onSubmit = (data: registerFormValues) => {
    register(data);
  };
  return (
    <FormProvider {...form}>
      <form
        onSubmit={handleSubmit(onSubmit)}
        className="flex flex-col gap-2 items-stretch w-[90%] mx-auto"
      >
        <div className="flex gap-3 justify-stretch">
          <FormInput
            name="name"
            className="h-12"
            containerClass="flex-1"
            placeholder={t("nameInputPlaceholder")}
          />
          <FormInput
            name="email"
            className="h-12"
            containerClass="flex-1"
            type="email"
            placeholder={t("emailInputPlaceholder")}
          />
        </div>
        <FormInput
          name="phone"
          className="h-12"
          placeholder={t("phoneInputPlaceholder")}
        />
        <div className="flex gap-3">
          <FormInput
            name="password"
            className="h-12"
            containerClass="flex-1"
            type="password"
            placeholder={t("passwordInputPlaceholder")}
          />
          <FormInput
            name="password_confirmation"
            className="h-12"
            containerClass="flex-1"
            type="password"
            placeholder={t("confirmPasswordInputPlaceholder")}
          />
        </div>
        <FormCheckbox
          id="terms-checkbox-basic"
          name="marketingCommunicationsConfirm"
          label={t("marketingCommunicationsCheckbox")}
        />
        <Button
          className={"h-12 bg-gray-2 w-full cursor-pointer"}
          disabled={!isDirty || !isValid || isRegistering}
          type="submit"
        >
          {t("continue")}
          {isRegistering && <Spinner />}
        </Button>
        {registerIsError && (
          <p className="text-xs text-destructive mt-1.5">
            {registerError?.message}
          </p>
        )}
        <p>
          {t("privacyMessage")}{" "}
          <Link href={"/privacy-policy"} className="text-blue-2">
            {t("privacyPolicy")}
          </Link>
        </p>
      </form>
    </FormProvider>
  );
}
