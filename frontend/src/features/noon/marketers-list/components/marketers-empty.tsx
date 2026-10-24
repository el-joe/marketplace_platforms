import { getTranslations } from "next-intl/server";
import { Users } from "lucide-react";

export default async function MarketersEmpty() {
  const t = await getTranslations("marketers");
  return (
    <div role="status" className="flex flex-col items-center gap-3 py-24 text-center">
      <div className="flex h-14 w-14 items-center justify-center rounded-full bg-gray-2">
        <Users className="h-6 w-6 text-gray" />
      </div>
      <p className="text-sm font-semibold text-gray">{t("emptyGender")}</p>
    </div>
  );
}
