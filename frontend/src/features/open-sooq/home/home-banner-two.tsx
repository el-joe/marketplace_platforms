import { Link } from "@/i18n/navigation";
import { getTranslations } from "next-intl/server";
import Image from "next/image";

export default async function HomeBannerTwo() {
  const t = await getTranslations("openSooq.home");
  return (
    <section id="homeBannerTwo" className="bg-gray-100 text-center">
      <div className="bg-gray-100 pt-8 pb-8 m-auto">
        <div className="flex m-auto container items-stretch gap-8">
          {/* Left column - Sell */}
          <div className="flex flex-col justify-center items-center wrapperImg w-1/3">
            <strong className="block text-[26px] leading-[26px]">
              {t("sellAnythingDescription")}
            </strong>
            <Image
              alt="Snap Photo . List It . Sell It"
              src="https://opensooqui2.os-cdn.com/prod/public/images/homePage/homeBanner-1.webp"
              width={600}
              height={400}
              loading="lazy"
              sizes="288px"
              className="w-[72%] h-auto"
              style={{ color: "transparent" }}
            />
            <button className="bg-green text-white w-full font-bold text-[27px] py-[22.35px] rounded-xl">
              {t("sellAnythingNow")}
            </button>
          </div>

          {/* Middle column - Stats */}
          <div className="flex flex-col gap-4 w-1/3 px-4 justify-stretch h-auto">
            <div className="rounded-[30px] py-[30px] bg-white flex-1 flex flex-col justify-center">
              <span className="block font-bold pb-4 leading-[43px] text-[43px]">
                {t("millions", { stats: 70 })}
              </span>
              {t("userVisits")}
            </div>
            <div className="rounded-[30px] py-[30px] bg-white py-[35px] flex-1 flex flex-col justify-center">
              <span className="block font-bold pb-4 leading-[43px] text-[43px]">
                {t("bestPrices")}
              </span>
              {t("postYourListingAndEarnMoney")}
            </div>
          </div>

          {/* Right column - Search */}
          <div className="flex flex-col justify-center items-center wrapperImg w-1/3">
            <strong className="text-blue-600 block text-[26px] leading-[26px]">
              {t("searchNowDescription")}
            </strong>
            <Image
              alt="Search . Call . Buy"
              src="https://opensooqui2.os-cdn.com/prod/public/images/homePage/homeBanner-2.webp"
              width={600}
              height={400}
              loading="lazy"
              sizes="288px"
              className="w-[72%] h-auto"
              style={{ color: "transparent" }}
            />
            <Link
              id="searchNowBtnSource"
              href="/en/find"
              className="bg-main text-light w-full font-bold rounded-8 text-[27px] py-[22.35px] rounded-xl"
            >
              {t("searchNow")}
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
}
