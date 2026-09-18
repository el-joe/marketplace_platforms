import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/src/components/ui/accordion";
import type { PageContentFaq } from "../../helpers/types";

type Props = {
  /**
   * Admin-managed FAQs (context = 'gift_cards') fetched from
   * GET /page-content/gift-cards. When empty (nothing configured yet),
   * the whole section is omitted rather than rendering an empty box.
   */
  faqs: PageContentFaq[];
};

export default function FaqSection({ faqs }: Props) {
  const t = useTranslations("giftCards");
  const locale = useLocale();

  if (faqs.length === 0) {
    return null;
  }

  return (
    <section className="py-10 bg-gray-2">
      <h2 className="text-center text-3xl text-light font-bold">
        {t("faqTitle")}
      </h2>
      <div className="max-w-[1000px] mx-auto mt-6">
        <Accordion className="bg-white rounded-md">
          {faqs.map((faq) => (
            <AccordionItem
              key={faq.id}
              value={faq.id}
              className={"border-border"}
            >
              <AccordionTrigger className="font-bold text-light px-4 py-4 cursor-pointer hover:no-underline">
                {locale === "ar" ? faq.question_ar : faq.question_en}
              </AccordionTrigger>
              <AccordionContent className={"px-4 py-2 text-gray"}>
                {locale === "ar" ? faq.answer_ar : faq.answer_en}
              </AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>
      </div>
    </section>
  );
}
