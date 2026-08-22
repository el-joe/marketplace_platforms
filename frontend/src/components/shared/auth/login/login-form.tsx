"use client";
import React from "react";
import { Button } from "../../../ui/button";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import FormInput from "../../../ui/form-inputs/form-input";
import { FormProvider, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { getLoginSchema, loginFormValues } from "./schema";
import { useAuthContext } from "@/src/providers/auth-provider";
import { Spinner } from "@/src/components/ui/spinner";

export default function LoginForm() {
  const t = useTranslations("authDialog");
  const { login, isLogging, loginError, loginIsError } = useAuthContext();

  const form = useForm<loginFormValues>({
    resolver: zodResolver(getLoginSchema(t)),
  });

  const {
    handleSubmit,
    formState: { isDirty, isValid },
  } = form;
  const onSubmit = (data: loginFormValues) => {
    login(data);
  };
  return (
    <FormProvider {...form}>
      <form
        onSubmit={handleSubmit(onSubmit)}
        className="flex flex-col gap-2 items-stretch w-8/12 mx-auto"
      >
        <FormInput
          name="email_or_phone"
          className="h-12 w-full"
          placeholder={t("loginInputPlaceholder")}
          autoFocus
        />
        <FormInput
          name="password"
          type="password"
          className="h-12 w-full"
          placeholder={t("passwordPlaceholder")}
        />
        <Button
          className={"h-12 bg-gray-2 w-full cursor-pointer"}
          disabled={!isDirty || !isValid || isLogging}
          type="submit"
        >
          {t("continue")}
          {isLogging && <Spinner />}
        </Button>
        {loginIsError && (
          <p className="text-xs text-destructive mt-1.5">
            {loginError?.message}
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
