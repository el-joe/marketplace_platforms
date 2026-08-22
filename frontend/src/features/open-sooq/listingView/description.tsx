import { getTranslations } from "next-intl/server";
import React from "react";

const desc = `
كيا سيراتو 2023
للبيع كيا سيراتو 2023
وارد الكويت
سياره بحاله كالجديده
حمايه داخليه وخارجيه
سيرفيس التوكيل
يوجد دهان على قطعه واحده وفرف امامي يمين`;

const specificationList = [
  { id: 1, label: "Memory Type", value: "MicroSD" },
  { id: 2, label: "SIM Count", value: "MicroSD" },
  { id: 3, label: "RAM Size", value: "MicroSD" },
  { id: 4, label: "Internal Memory", value: "MicroSD" },
  { id: 5, label: "Version", value: "MicroSD" },
  { id: 6, label: "Display Resolution", value: "MicroSD" },
  { id: 7, label: "Display Resolution", value: "MicroSD" },
  { id: 8, label: "Charging Type", value: "5 MP" },
  { id: 11, label: "Memory Type", value: "MicroSD" },
  { id: 21, label: "SIM Count", value: "MicroSD" },
  { id: 31, label: "RAM Size", value: "MicroSD" },
  { id: 41, label: "Internal Memory", value: "MicroSD" },
  { id: 51, label: "Version", value: "MicroSD" },
  { id: 61, label: "Display Resolution", value: "MicroSD" },
  { id: 71, label: "Display Resolution", value: "MicroSD" },
  { id: 81, label: "Charging Type", value: "MicroSD" },
  { id: 82, label: "Charging Type", value: "MicroSD" },
];

export default async function ListingDescription() {
  const t = await getTranslations("openSooq.listingView");
  const middleIndex = Math.ceil(specificationList.length / 2);

  const leftColumn = specificationList.slice(0, middleIndex);
  const rightColumn = specificationList.slice(middleIndex);
  return (
    <>
      <h3 className="text-2xl font-bold text-light mb-4">{t("description")}</h3>
      {/* description */}
      <p className="text-secondary">{desc}</p>
      {/* specifications table */}
      <div className="md:flex gap-x-4 flex-wrap text-xs md:text-sm xl:text-base mt-4">
        {/*left col  */}
        <div className="md:w-[calc((100%-16px)/2)]">
          {leftColumn.map((sp) => (
            <div
              key={sp.id}
              className="even:bg-gray-5 odd:bg-gray-4 flex p-3 gap-3"
            >
              <p className="w-[45%] md:w-[30%] text-light ">{sp.label}</p>
              <p className="">{sp.value}</p>
            </div>
          ))}
        </div>
        {/*right col  */}
        <div className="md:w-[calc((100%-16px)/2)]">
          {rightColumn.map((sp) => (
            <div
              key={sp.id}
              className="even:bg-gray-5 odd:bg-gray-4 flex p-3 gap-3"
            >
              <p className="w-[45%] md:w-[30%] text-light ">{sp.label}</p>
              <p className="">{sp.value}</p>
            </div>
          ))}
        </div>
      </div>
    </>
  );
}
