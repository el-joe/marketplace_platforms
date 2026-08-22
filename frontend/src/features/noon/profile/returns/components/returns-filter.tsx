"use client";

import { Input } from "@/src/components/ui/base-inputs/input";
import { Select } from "@/src/components/ui/base-inputs/select";
import { Search } from "lucide-react";
import { useTranslations } from "next-intl";

export default function ReturnsFilter() {
  const t = useTranslations("profile");

  return (
    <div className="flex items-center gap-3">
      <Input
        startIcon={<Search />}
        placeholder={t("findItems")}
        className="w-[400px] rounded-none h-12 bg-white!"
      />

      <Select
        defaultValue="3-months"
        triggerClass="w-[230px]! rounded-none h-12! bg-white! justify-between"
        items={[{ label: t("last3Months"), value: "3-months" }]}
      />
    </div>
  );
}
