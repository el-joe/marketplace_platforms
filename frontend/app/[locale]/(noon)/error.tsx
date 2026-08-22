"use client";

import { useTranslations } from "next-intl";
import Image from "next/image";

export default function Error() {
  const t = useTranslations();
  return (
    <div className="flex flex-col justify-center items-center py-22">
      <Image
        src={"/images/generic-error.svg"}
        alt=""
        width={340}
        height={260}
      />
      <h4 className="text-xl font-bold">{t("anErrorHasOccurred")}</h4>
      <p>{t("weAreVerySorryButSomethingHasGoneWrongPleaseTryAgain")}</p>
    </div>
  );
}
