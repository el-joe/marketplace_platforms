"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { createSpecialRequest } from "../api/special-requests.actions";
import {
  getSpecialRequestSchema,
  type SpecialRequestFormValues,
} from "./special-request.schema";
import { ALL_CITIES } from "./constants";

export function useFormActions() {
  const t = useTranslations("specialRequests");
  const [notified, setNotified] = useState<number | null>(null);

  const form = useForm<SpecialRequestFormValues>({
    resolver: zodResolver(getSpecialRequestSchema(t)),
    defaultValues: {
      category_id: "",
      city_id: ALL_CITIES,
      title_en: "",
      description_en: "",
      budget: "",
      budget_currency: "",
    },
  });

  const onSubmit = async (v: SpecialRequestFormValues) => {
    try {
      const res = await createSpecialRequest({
        category_id: v.category_id,
        city_id: v.city_id === ALL_CITIES ? null : v.city_id,
        title_en: v.title_en,
        description_en: v.description_en,
        budget: v.budget ? Number(v.budget) : null,
        budget_currency: v.budget ? v.budget_currency || null : null,
      });
      setNotified(res.brokers_notified);
    } catch {
      toast.error(t("submitFailed"));
    }
  };

  return { form, onSubmit, notified, isSaving: form.formState.isSubmitting };
}
