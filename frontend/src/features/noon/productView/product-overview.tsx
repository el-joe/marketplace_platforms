import { Separator } from "@/src/components/ui/separator";
import { getTranslations } from "next-intl/server";
import React from "react";
import { IProductDetails } from "./types";
import getLocale from "@/src/helpers/getLocale";

type props = {
  overviewData: {
    overview: IProductDetails["product"]["description"];
    highlights: IProductDetails["product"]["highlights"];
    specification: IProductDetails["product"]["specifications"];
  };
};

export default async function ProductOverview({ overviewData }: props) {
  const t = await getTranslations("productView");
  const middleIndex = Math.ceil(overviewData.specification.length / 2);
  const locale = await getLocale();

  const leftColumn = overviewData.specification.slice(0, middleIndex);
  const rightColumn = overviewData.specification.slice(middleIndex);
  return (
    <>
      <h3 className="py-5 text-2xl mt-8 font-bold text-light">
        {t("productOverview")}
      </h3>
      {/* overview */}
      <Separator />
      <div
        dangerouslySetInnerHTML={{ __html: overviewData.overview[locale] as string }}
        className="pt-8 pb-5 text-secondary ps-6"
      />
      {/* highlights */}
      {!!overviewData.highlights.length && (
        <>
          <h5 className="text-sm text-light uppercase">{t("highlights")}</h5>
          <ul className="pt-8 pb-5 text-secondary list-disc ps-6 ">
            {overviewData.highlights.map((ov, i) => (
              <li key={i} className="not-last:mb-1">
                {ov}
              </li>
            ))}
          </ul>
        </>
      )}
      {/* specifications table */}
      {!!overviewData.specification.length && (
        <>
          <h5 className="text-sm text-light uppercase mb-3">
            {t("specification")}
          </h5>
          <div className="md:flex gap-x-4 flex-wrap text-xs md:text-sm xl:text-base">
            {/*left col  */}
            <div className="md:w-[calc((100%-16px)/2)]">
              {leftColumn.map((sp, i) => (
                <div
                  key={i}
                  className="even:bg-gray-5 odd:bg-gray-4 flex p-3 gap-3"
                >
                  <p className="w-[45%] md:w-[30%] text-light ">{sp.label}</p>
                  <p className="">{sp.value}</p>
                </div>
              ))}
            </div>
            {/*right col  */}
            <div className="md:w-[calc((100%-16px)/2)]">
              {rightColumn.map((sp, i) => (
                <div
                  key={i}
                  className="even:bg-gray-5 odd:bg-gray-4 flex p-3 gap-3"
                >
                  <p className="w-[45%] md:w-[30%] text-light ">{sp.label}</p>
                  <p className="">{sp.value}</p>
                </div>
              ))}
            </div>
          </div>
        </>
      )}
    </>
  );
}
