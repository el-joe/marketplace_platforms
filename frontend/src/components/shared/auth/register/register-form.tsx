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
import GenderRadioGroup from "./gender-radio-group";

export default function RegisterForm() {
  const t = useTranslations("authDialog");
  const { register, isRegistering, registerError, registerIsError } =
    useAuthContext();

  const form = useForm<registerFormValues>({
    resolver: zodResolver(getRegisterSchema(t)),
    // Empty strings (not undefined) so an untouched field shows its own localized message on submit.
    defaultValues: {
      name: "",
      email: "",
      phone: "",
      password: "",
      password_confirmation: "",
      marketingCommunicationsConfirm: false,
    },
  });

  const {
    handleSubmit,
    formState: { errors, isSubmitted },
  } = form;
  // Continue stays clickable; on submit each invalid field shows its reason and the first one gets focus.
  const hasErrors = isSubmitted && Object.keys(errors).length > 0;
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
        <GenderRadioGroup
          legend={t("genderLabel")}
          options={[
            { value: "male", label: t("genderMale") },
            { value: "female", label: t("genderFemale") },
            { value: "", label: t("genderUnspecified") },
          ]}
        />
        <FormCheckbox
          id="terms-checkbox-basic"
          name="marketingCommunicationsConfirm"
          label={t("marketingCommunicationsCheckboxOptional")}
        />
        <Button
          className={"h-12 bg-gray-2 w-full cursor-pointer"}
          disabled={isRegistering}
          type="submit"
        >
          {t("continue")}
          {isRegistering && <Spinner />}
        </Button>
        {hasErrors && (
          <p role="alert" className="text-xs text-destructive mt-1.5">
            {t("registerFixErrors")}
          </p>
        )}
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
