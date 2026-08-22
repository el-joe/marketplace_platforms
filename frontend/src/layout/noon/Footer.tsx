import { Link } from "@/i18n/navigation";
import { footerLinks, payMethodsImages, socialLinks } from "@/public/dummyData";
import { getTranslations } from "next-intl/server";
import Image from "next/image";
import React from "react";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/src/components/ui/accordion";
import LowerFooter from "../shared/lower-footer";

const Footer = async () => {
  const t = await getTranslations("footer");
  return (
    <footer>
      {/* upper footer */}
      <div className="container pt-3 pb-6">
        {/* links */}
        <div className="hidden md:flex items-start gap-4 justify-between mb-8">
          {footerLinks.map((linksG) => (
            <div key={linksG.linksType}>
              <h5 className="font-bold text-light">{linksG.linksType}</h5>
              <ul className="flex flex-col gap-1">
                {linksG.links.map((link) => (
                  <li key={link}>
                    <Link href={"/"} className="text-gray text-sm">
                      {link}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        <Accordion className="md:hidden">
          {footerLinks.map((linksG) => (
            <AccordionItem
              key={linksG.linksType}
              value={linksG.linksType}
              className={"border-b-gray-2"}
            >
              <AccordionTrigger>{linksG.linksType}</AccordionTrigger>
              <AccordionContent>
                <ul className="flex flex-col gap-1">
                  {linksG.links.map((link) => (
                    <li key={link}>
                      <Link
                        href={"/"}
                        className="text-gray text-sm decoration-0!"
                        style={{ textDecoration: "none" }}
                      >
                        {link}
                      </Link>
                    </li>
                  ))}
                </ul>
              </AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>
        {/* get the app and social links */}
        <div className="flex flex-col md:flex-row justify-around items-center">
          {/* get app */}
          <div>
            <h5 className="text-light font-medium mb-3 text-center">
              {t("shopONTheGo")}
            </h5>
            <div className="flex items-center gap-2 justify-center">
              <Image
                src={
                  "https://f.nooncdn.com/s/app/com/common/images/logos/app-store.svg"
                }
                alt=""
                width={84}
                height={26}
              />
              <Image
                src={
                  "https://f.nooncdn.com/s/app/com/common/images/logos/google-play.svg"
                }
                alt=""
                width={84}
                height={26}
              />
              <Image
                src={
                  "https://f.nooncdn.com/s/app/com/noon/images/Huawei-icon.png"
                }
                alt=""
                width={84}
                height={26}
                className="w-21 h-6.5"
              />
            </div>
          </div>
          {/* social links */}
          <div>
            <h5 className="text-light font-medium mb-3 text-center">
              {t("contactWithUs")}
            </h5>
            <div className="flex items-center gap-1.5 justify-center">
              {socialLinks.map((link) => (
                <Link
                  href={"/"}
                  key={link.link}
                  className="aspect-square w-10 rounded-full bg-yellow-400 grid place-items-center"
                >
                  <Image src={link.icon} width={20} height={20} alt="" />
                </Link>
              ))}
            </div>
          </div>
        </div>
      </div>
      <LowerFooter
        paymentMethods={payMethodsImages}
        className="container pb-7 pt-4 flex flex-col md:flex-row justify-between gap-2 items-center bg-gray-2"
      />
    </footer>
  );
};

export default Footer;
