import { useTranslations } from "next-intl";
import { cn } from "@/src/lib/utils";

export type CustomAttributeValue = {
  label: string;
  unit?: string | null;
  type?: string;
  value: string;
};

/** Read-only list of the measurements/options a customer entered for a cart or order line. */
export default function CustomAttributeValues({
  values,
  className,
}: {
  values?: CustomAttributeValue[] | null;
  className?: string;
}) {
  const t = useTranslations("sizeGuide");
  if (!values?.length) return null;

  return (
    <dl className={cn("flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray", className)}>
      {values.map((v) => (
        <div key={`${v.label}-${v.value}`} className="flex gap-1">
          <dt className="font-medium">{v.label}:</dt>
          <dd className="text-black">
            {v.type === "checkbox" ? (v.value === "1" ? t("yes") : t("no")) : v.value}
            {v.unit && v.type !== "checkbox" ? ` ${v.unit}` : ""}
          </dd>
        </div>
      ))}
    </dl>
  );
}
