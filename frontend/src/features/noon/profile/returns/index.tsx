import { getTranslations } from "next-intl/server";
import { Button } from "@/src/components/ui/button";
import ReturnsEmptyState from "./components/returns-empty-state";
import ReturnsList from "./components/returns-list";
import ReturnsFilter from "./components/returns-filter";
import { getReturns } from "./api/returns.actions";

export default async function Returns() {
  const t = await getTranslations("profile");

  const { items: returns } = await getReturns();

  return (
    <>
      <h1 className="text-[28px] font-bold  text-light">{t("returnsTitle")}</h1>
      <p className="text-sm text-gray mt-1">{t("returnsSubtitle")}</p>

      <div className="mt-3 flex items-center justify-between">
        <Button className="bg-blue-3 text-white uppercase font-semibold px-6 h-11 rounded-md">
          {t("createNewReturn")}
        </Button>

        <ReturnsFilter />
      </div>

      {returns.length === 0 ? (
        <ReturnsEmptyState />
      ) : (
        <ReturnsList returns={returns} />
      )}
    </>
  );
}
