import { AlertCircleIcon, MailIcon } from "lucide-react";
import { getTranslations } from "next-intl/server";
import React from "react";

const HelpSection = async () => {
  const t = await getTranslations();
  return (
    <div className="md:bg-gray-2 border-y border-gray-3">
      <div className="container py-4 flex flex-col md:flex-row gap-4 justify-center md:justify-between items-center">
        {/* the left col */}
        <div className="text-center md:text-start">
          <h2 className="text-light font-semibold text-xl mb-1 md:mb-3">
            {t("we'reAlwaysHereToHelp")}
          </h2>
          <p className="text-gray text-sm hidden md:block">
            {t("reachOutToUsThroughAnyOfTheseSupportChannels")}
          </p>
        </div>
        {/* the right col */}
        <div className="flex flex-col md:flex-row items-center gap-4">
          {/*  */}
          <div className="flex items-center gap-2">
            <span className="w-8 aspect-square bg-white rounded-full grid place-items-center border border-gray">
              <AlertCircleIcon className="size-4 text-gray" />
            </span>
            <div>
              <p className="text-gray text-sm">{t("helpCenter")}</p>
              <h3 className="text-xl text-light font-semibold">help.co.com</h3>
            </div>
          </div>
          {/*  */}
          <div className="flex items-center gap-2">
            <span className="w-8 aspect-square bg-white rounded-full grid place-items-center border border-gray">
              <MailIcon className="size-4 text-gray" />
            </span>
            <div>
              <p className="text-gray text-sm">{t("emailSupport")}</p>
              <h3 className="text-xl text-light font-semibold">help.co.com</h3>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default HelpSection;
