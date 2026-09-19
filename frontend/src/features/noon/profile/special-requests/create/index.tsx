"use client";

import { Controller } from "react-hook-form";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Button } from "@/src/components/ui/button";
import { Input } from "@/src/components/ui/base-inputs/input";
import { Select } from "@/src/components/ui/base-inputs/select";
import { Textarea } from "@/src/components/ui/base-inputs/textarea";
import useLocale from "@/src/hooks/use-locale";
import { useCurrencies } from "@/src/hooks/use-currencies";
import { getCategoriesTreeService } from "@/src/layout/noon/header/api/get";
import { getCities } from "../api/special-requests.actions";
import { ALL_CITIES } from "../helpers/constants";
import { flattenLeafCategories } from "../helpers/flatten-categories";
import { useFormActions } from "../helpers/use-form-actions";

export default function CreateSpecialRequest() {
  const t = useTranslations("specialRequests");
  const locale = useLocale();
  const { form, onSubmit, notified, isSaving } = useFormActions();
  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = form;

  const categories = useQuery({
    queryKey: ["categories-tree"],
    queryFn: getCategoriesTreeService,
  });
  const cities = useQuery({ queryKey: ["cities"], queryFn: getCities });
  const currencies = useCurrencies();

  if (notified !== null) {
    return (
      <div className="mx-auto max-w-xl space-y-4 py-16 text-center">
        <h1 className="text-xl font-bold">{t("posted")}</h1>
        <p className="text-sm text-gray">
          {t("brokersNotified", { count: notified })}
        </p>
        <Button
          render={<Link href="/special-requests" />}
          nativeButton={false}
          className="h-11 rounded-md bg-blue-3 px-8 font-semibold text-white"
        >
          {t("viewMyRequests")}
        </Button>
      </div>
    );
  }

  const err = (m?: string) =>
    m ? <p className="text-xs text-red">{m}</p> : null;

  return (
    <form
      onSubmit={handleSubmit(onSubmit)}
      className="mx-auto max-w-xl space-y-5 py-8"
    >
      <h1 className="text-xl font-bold">{t("newRequest")}</h1>

      <div className="space-y-1">
        <label className="text-sm font-medium">{t("category")}</label>
        <Controller
          control={control}
          name="category_id"
          render={({ field }) => (
            <Select
              value={field.value || null}
              onValueChange={(v) => field.onChange(v ?? "")}
              triggerClass="w-full h-11 justify-between"
              placeholder={t("selectCategory")}
              items={flattenLeafCategories(categories.data ?? [], locale)}
            />
          )}
        />
        {err(errors.category_id?.message)}
      </div>

      <div className="space-y-1">
        <label className="text-sm font-medium">{t("city")}</label>
        <Controller
          control={control}
          name="city_id"
          render={({ field }) => (
            <Select
              value={field.value}
              onValueChange={(v) => field.onChange(v ?? ALL_CITIES)}
              triggerClass="w-full h-11 justify-between"
              items={[
                { label: t("allCities"), value: ALL_CITIES },
                ...(cities.data ?? []).map((c) => ({
                  label: c.name,
                  value: c.id,
                })),
              ]}
            />
          )}
        />
      </div>

      <div className="space-y-1">
        <label className="text-sm font-medium">{t("title")}</label>
        <Input maxLength={255} {...register("title_en")} />
        {err(errors.title_en?.message)}
      </div>

      <div className="space-y-1">
        <label className="text-sm font-medium">{t("description")}</label>
        <Textarea className="min-h-[120px]" {...register("description_en")} />
        {err(errors.description_en?.message)}
      </div>

      <div className="grid grid-cols-[1fr_120px] gap-3">
        <div className="space-y-1">
          <label className="text-sm font-medium">
            {t("budget")} ({t("optional")})
          </label>
          <Input inputMode="numeric" {...register("budget")} />
          {err(errors.budget?.message)}
        </div>
        <div className="space-y-1">
          <label className="text-sm font-medium">{t("currency")}</label>
          <Controller
            control={control}
            name="budget_currency"
            render={({ field }) => (
              <Select
                value={field.value || null}
                onValueChange={(v) => field.onChange(v ?? "")}
                triggerClass="w-full h-11 justify-between"
                items={(currencies.data ?? []).map((c) => ({
                  label: c.code,
                  value: c.code,
                }))}
              />
            )}
          />
        </div>
      </div>

      <Button
        type="submit"
        disabled={isSaving}
        className="h-12 w-full rounded-md bg-blue-3 font-semibold text-white"
      >
        {t("submit")}
      </Button>
    </form>
  );
}
