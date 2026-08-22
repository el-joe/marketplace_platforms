import { useTranslations } from "next-intl";

export const AdBadge = () => {
  const t = useTranslations();
  return (
    <div className="p-1 text-xs rounded-md text-light bg-white opacity-60 absolute right-3 bottom-3">
      {t("ad")}
    </div>
  );
};
