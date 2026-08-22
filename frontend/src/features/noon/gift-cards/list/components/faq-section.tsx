import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/src/components/ui/accordion";

export default function FaqSection() {
  const t = useTranslations("giftCards");

  const faqs = [
    { questionKey: "faqWhatIsGiftCard", answerKey: "faqWhatIsGiftCardAnswer" },
    { questionKey: "faqHowDelivered", answerKey: "faqHowDeliveredAnswer" },
    { questionKey: "faqHowLongValid", answerKey: "faqHowLongValidAnswer" },
  ];

  return (
    <section className="py-10 bg-gray-2">
      <h2 className="text-center text-3xl text-light font-bold">
        {t("faqTitle")}
      </h2>
      <div className="max-w-[1000px] mx-auto mt-6">
        <Accordion className="bg-white rounded-md">
          {faqs.map((faq) => (
            <AccordionItem
              key={faq.questionKey}
              value={faq.questionKey}
              className={"border-border"}
            >
              <AccordionTrigger className="font-bold text-light px-4 py-4 cursor-pointer hover:no-underline">
                {t(faq.questionKey)}
              </AccordionTrigger>
              <AccordionContent className={"px-4 py-2 text-gray"}>
                {t(faq.answerKey)}
              </AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>
        <div className="mt-4 flex items-center justify-between mx-auto">
          <Link href="/" className="text-sm text-blue-3 underline">
            {t("viewAllFaqs")}
          </Link>
          <Link href="/" className="text-sm text-blue-3 underline">
            {t("readTerms")}
          </Link>
        </div>
      </div>
    </section>
  );
}
