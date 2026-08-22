"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import type { ICustomerProfile } from "@/types";
import { updateProfile } from "@/src/services/profile";
import { getProfileSchema, type ProfileFormValues } from "./profile.schema";
import { toFormValues } from "./to-form-values";
import { useAuthContext } from "@/src/providers/auth-provider";

export function useFormActions(profile: ICustomerProfile) {
  const t = useTranslations("profile");

  const router = useRouter();

  const { updateCustomer } = useAuthContext();

  const [isSaving, setIsSaving] = useState(false);

  const form = useForm<ProfileFormValues>({
    resolver: zodResolver(getProfileSchema(t)),
    defaultValues: toFormValues(profile),
    mode: "onChange",
  });

  const onSubmit = async (data: ProfileFormValues) => {
    setIsSaving(true);

    const { firstName, lastName, ...rest } = data;

    try {
      const updated = await updateProfile({
        ...rest,
        name: `${firstName} ${lastName}`.trim(),
      });

      updateCustomer(updated);

      router.refresh();
    } catch {
      toast.error(t("profileUpdateFailed"));
    } finally {
      setIsSaving(false);
    }
  };

  return { form, isSaving, onSubmit };
}
