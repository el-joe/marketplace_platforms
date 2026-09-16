import { Link } from "@/i18n/navigation";
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
import { getFooterData } from "@/src/services/footer";
import getLocale from "@/src/helpers/getLocale";

const SOCIAL_ICONS: Record<string, string> = {
  facebook: "/images/facebook-social.svg",
  x: "/images/Twitter-X-social.svg",
  twitter: "/images/Twitter-X-social.svg",
};

const APP_STORE_ICONS: Record<string, string> = {
  app_store:
    "https://f.nooncdn.com/s/app/com/common/images/logos/app-store.svg",
  google_play:
    "https://f.nooncdn.com/s/app/com/common/images/logos/google-play.svg",
  huawei: "https://f.nooncdn.com/s/app/com/noon/images/Huawei-icon.png",
};

const Footer = async () => {
  const t = await getTranslations("footer");
  const locale = await getLocale();
  const {
    categories,
    social_links,
    bottom_nav_links,
    app_store_links,
    payment_methods,
  } = await getFooterData();

  const footerLinks = categories.map((category) => ({
    id: category.id,
    link: category.link,
    linksType: category.name[locale] ?? category.name.en ?? "",
    links: category.children.map((child) => ({
      id: child.id,
      link: child.link,
      label: child.name[locale] ?? child.name.en ?? "",
    })),
  }));

  return (
    <footer>
      {/* upper footer */}
      <div className="container pt-3 pb-6">
        {/* links */}
        <div className="hidden md:flex items-start gap-4 justify-between mb-8">
          {footerLinks.map((linksG) => (
            <div key={linksG.id}>
              <h5 className="font-bold text-light">{linksG.linksType}</h5>
              <ul className="flex flex-col gap-1">
                {linksG.links.map((link) => (
                  <li key={link.id}>
                    <Link href={link.link} className="text-gray text-sm">
                      {link.label}
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
              key={linksG.id}
              value={linksG.id}
              className={"border-b-gray-2"}
            >
              <AccordionTrigger>{linksG.linksType}</AccordionTrigger>
              <AccordionContent>
                <ul className="flex flex-col gap-1">
                  {linksG.links.map((link) => (
                    <li key={link.id}>
                      <Link
                        href={link.link}
                        className="text-gray text-sm decoration-0!"
                        style={{ textDecoration: "none" }}
                      >
                        {link.label}
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
          {app_store_links.length > 0 && (
            <div>
              <h5 className="text-light font-medium mb-3 text-center">
                {t("shopONTheGo")}
              </h5>
              <div className="flex items-center gap-2 justify-center">
                {app_store_links.map((link) => (
                  <Link href={link.url ?? "/"} key={link.id}>
                    <Image
                      src={
                        link.icon ??
                        APP_STORE_ICONS[link.platform ?? ""] ??
                        APP_STORE_ICONS.app_store
                      }
                      alt=""
                      width={84}
                      height={26}
                    />
                  </Link>
                ))}
              </div>
            </div>
          )}
          {/* social links */}
          {social_links.length > 0 && (
            <div>
              <h5 className="text-light font-medium mb-3 text-center">
                {t("contactWithUs")}
              </h5>
              <div className="flex items-center gap-1.5 justify-center">
                {social_links.map((link) => (
                  <Link
                    href={link.url ?? "/"}
                    key={link.id}
                    className="aspect-square w-10 rounded-full bg-yellow-400 grid place-items-center"
                  >
                    <Image
                      src={
                        link.icon ??
                        SOCIAL_ICONS[link.platform ?? ""] ??
                        "/images/facebook-social.svg"
                      }
                      width={20}
                      height={20}
                      alt=""
                    />
                  </Link>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
      <LowerFooter
        paymentMethods={payment_methods}
        bottomNavLinks={bottom_nav_links}
        locale={locale}
        className="container pb-7 pt-4 flex flex-col md:flex-row justify-between gap-2 items-center bg-gray-2"
      />
    </footer>
  );
};

export default Footer;
