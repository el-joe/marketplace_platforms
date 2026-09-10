import { useTranslations } from "next-intl";

export const AdBadge = ({ label }: { label?: string }) => {
  const t = useTranslations();
  return (
    <div
      className="p-1 text-xs rounded-md text-light bg-white opacity-60 absolute right-3 bottom-3"
      title={label}
      aria-label={label ?? t("ad")}
    >
      {t("ad")}
    </div>
  );
};
