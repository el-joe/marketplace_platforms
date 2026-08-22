import { getTranslations } from "next-intl/server";
import { LuggageIcon, ShieldCheckIcon, CheckCircle2Icon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/src/components/ui/accordion";

type Props = {
  included: string[];
};

export default async function GuidelinesAccordion({ included }: Props) {
  const t = await getTranslations("flights.packageDetails");

  return (
    <section>
      <h2 className="text-2xl font-bold text-primary mb-8">
        {t("guidelinesTitle")}
      </h2>

      <Accordion defaultValue={["included"]} multiple className="gap-4">
        <Card className="border border-border shadow-sm overflow-hidden">
          <AccordionItem value="baggage" className="border-none">
            <AccordionTrigger className="p-6 hover:no-underline hover:bg-gray-2">
              <span className="flex items-center gap-4 text-primary font-bold">
                <LuggageIcon className="size-5 text-blue-3" />
                {t("baggageTitle")}
              </span>
            </AccordionTrigger>
            <AccordionContent className="px-6">
              <p className="text-sm text-light leading-relaxed border-t border-border pt-4">
                {t("baggageBody")}
              </p>
            </AccordionContent>
          </AccordionItem>
        </Card>

        <Card className="border border-border shadow-sm overflow-hidden">
          <AccordionItem value="included" className="border-none">
            <AccordionTrigger className="p-6 hover:no-underline hover:bg-gray-2">
              <span className="flex items-center gap-4 text-primary font-bold">
                <CheckCircle2Icon className="size-5 text-blue-3" />
                {t("includedTitle")}
              </span>
            </AccordionTrigger>
            <AccordionContent className="px-6">
              <ul className="flex flex-col gap-2 border-t border-border pt-4">
                {included.map((item) => (
                  <li
                    key={item}
                    className="text-sm text-light leading-relaxed"
                  >
                    — {item}
                  </li>
                ))}
              </ul>
            </AccordionContent>
          </AccordionItem>
        </Card>

        <Card className="border border-border shadow-sm overflow-hidden">
          <AccordionItem value="terms" className="border-none">
            <AccordionTrigger className="p-6 hover:no-underline hover:bg-gray-2">
              <span className="flex items-center gap-4 text-primary font-bold">
                <ShieldCheckIcon className="size-5 text-blue-3" />
                {t("termsTitle")}
              </span>
            </AccordionTrigger>
            <AccordionContent className="px-6">
              <p className="text-sm text-light leading-relaxed border-t border-border pt-4">
                {t("termsBody")}
              </p>
            </AccordionContent>
          </AccordionItem>
        </Card>
      </Accordion>
    </section>
  );
}
