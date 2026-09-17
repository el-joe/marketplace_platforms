import { DynamicLayout } from "@/src/components/shared/page-builder";
import { getHomeService } from "./api/get";
import Image from "next/image";
import { getTranslations } from "next-intl/server";

export default async function Home() {
  const homeData = await getHomeService();
  const t = await getTranslations("home");
  return (
    <div className="bg-white">
      {homeData.data.page_builder.sections.length === 0 && (
        <Image
          src="/images/page_construction.png"
          alt={t("pageUnderConstruction")}
          width={500}
          height={500}
          className="mx-auto my-20 h-[70vh] object-contain"
        />
      )}
      {homeData.data.page_builder.sections.map((e) => (
        <DynamicLayout key={e.id} section={e} />
      ))}
    </div>
  );
}
