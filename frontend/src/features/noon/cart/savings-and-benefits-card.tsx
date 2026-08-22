import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import useLocale from "@/src/hooks/use-locale";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useTranslations } from "next-intl";
import Image from "next/image";

const savingsData = [
  {
    id: 1,
    image: "https://a.nooncdn.com/rn/noon/sales/tamara-v2-en.svg",
    body: "Pay in 6 payments of  549.67",
  },
  {
    id: 2,
    image: "https://a.nooncdn.com/rn/noon/sales/tabby-v2-en.svg",
    body: "Split in up to 12 payments",
  },
  {
    id: 3,
    image: "https://a.nooncdn.com/rn/noon/sales/bank-emis-v2-en.svg",
    body: "Pay in equal monthly installments with your bank",
  },
];
export default function SavingsAdnBenefitsCard() {
  const t = useTranslations("cart");
  const locale = useLocale();
  return (
    <div className="p-4 rounded-[16px] bg-white">
      <h3 className="font-bold mb-3">
        {t("savingsAndBenefits")}
        <span className="text-xs text-gray"> (This is dummy data)</span>
      </h3>
      <div className="flex gap-2 items-stretch">
        {savingsData.map((s) => (
          <div key={s.id} className="bg-gray-2 rounded-lg p-2 w-1/3 flex-1">
            <div className="flex justify-between items-center">
              <Image
                src={s.image}
                alt="saving icon"
                width={54}
                height={44}
                className="w-10 aspect-video"
              />
              {locale === "ar" ? (
                <ChevronLeft size={"16px"} />
              ) : (
                <ChevronRight size={"16px"} />
              )}
            </div>
            <p className="text-xs mt-2">{s.body}</p>
          </div>
        ))}
      </div>
      {/* benefits list */}
      <div className="bg-gray-2 rounded-lg p-2 mb-2 mt-3 flex items-center gap-2">
        <Image
          src={
            "https://a.nooncdn.com/rn/noon/sales/enbd-cobrand-card-v2-en.svg"
          }
          alt="benefit icon"
          width={50}
          height={40}
          className="w-11 aspect-video"
        />
        <p className="text-xs text-gray">
          Earn <Price currentPrice={164.2} size="xs" />{" "}
          <span className="text-[#3e01a4] text-sm uppercase font-bold">
            Ca$hBack
          </span>{" "}
          with noon one credit card
        </p>
        <Button className={"ms-auto text-blue text-sm"} variant={"ghost"}>
          {t("apply")}
          {locale === "ar" ? <ChevronLeft /> : <ChevronRight />}
        </Button>
      </div>
      <div className="bg-gray-2 rounded-lg p-2 flex items-center gap-2">
        <Image
          src={
            "https://a.nooncdn.com/rn/noon/sales/enbd-cobrand-card-v2-en.svg"
          }
          alt="benefit icon"
          width={50}
          height={40}
          className="w-11 aspect-video"
        />
        <p className="text-xs text-gray">
          Earn <Price currentPrice={164.2} size="xs" />{" "}
          <span className="text-[#3e01a4] text-sm uppercase font-bold">
            Ca$hBack
          </span>{" "}
          with noon one credit card
        </p>
        <Button className={"ms-auto text-blue text-sm"} variant={"ghost"}>
          {t("apply")}
          {locale === "ar" ? <ChevronLeft /> : <ChevronRight />}
        </Button>
      </div>
    </div>
  );
}
