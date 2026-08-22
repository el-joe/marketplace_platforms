"use client";

import type { ReactNode } from "react";
import { FormProvider } from "react-hook-form";
import { useTranslations } from "next-intl";
import type { ICustomerProfile } from "@/types";
import { Button } from "@/src/components/ui/button";
import { useFormActions } from "../helpers/use-form-actions";

type Props = {
  children: ReactNode;
  profile: ICustomerProfile;
};

export default function ProfileForm({ children, profile }: Props) {
  const t = useTranslations("profile");

  const { form, isSaving, onSubmit } = useFormActions(profile);

  const {
    formState: { isDirty, isValid },
  } = form;

  return (
    <FormProvider {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)}>
        {children}

        <Button
          type="submit"
          disabled={!isDirty || !isValid || isSaving}
          className="bg-blue-3 text-white uppercase font-semibold px-10 h-12 rounded-md mt-6 disabled:opacity-50"
          isLoading={isSaving}
          text={t("updateProfile")}
        />
      </form>
    </FormProvider>
  );
}
