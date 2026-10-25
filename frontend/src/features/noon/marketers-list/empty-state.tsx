import { getTranslations } from "next-intl/server";
import Image from "next/image";
import React from "react";

export default async function EmptyState() {
  const t = await getTranslations("marketers");
  return (
    <div className="flex flex-col items-center">
      <Image
        src="/images/empty-result.webp"
        alt=""
        width={1280}
        height={1280}
        className="w-md"
      />
      <p className="text-lg">{t("emptyGender")}</p>
    </div>
  );
}
