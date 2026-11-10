import { getTranslations } from "next-intl/server";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import getLocale from "@/src/helpers/getLocale";
import { localized } from "./helpers/localized";
import type { StaticPage } from "./helpers/types";

type Props = {
  page: StaticPage;
};

/**
 * Rich-text styles for the admin-authored HTML (no typography plugin in this
 * project). Logical properties (ps-, border-s) keep lists/quotes RTL-correct.
 */
const CONTENT_CLASSES = [
  "text-secondary leading-7 break-words",
  "[&_h1]:text-2xl [&_h1]:font-bold [&_h1]:text-light [&_h1]:mt-8 [&_h1]:mb-3",
  "[&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-light [&_h2]:mt-8 [&_h2]:mb-3",
  "[&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-light [&_h3]:mt-6 [&_h3]:mb-2",
  "[&_h4]:font-semibold [&_h4]:text-light [&_h4]:mt-4 [&_h4]:mb-2",
  "[&_p]:mb-4 [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:ps-6 [&_ol]:ps-6 [&_ul]:mb-4 [&_ol]:mb-4 [&_li]:mb-1",
  "[&_a]:underline [&_a]:text-main",
  "[&_blockquote]:border-s-4 [&_blockquote]:ps-4 [&_blockquote]:my-4 [&_blockquote]:text-gray",
  "[&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg [&_img]:my-4",
  "[&_table]:w-full [&_table]:my-4 [&_table]:text-sm [&_td]:border [&_th]:border [&_td]:p-2 [&_th]:p-2 [&_th]:text-start",
  "[&_hr]:my-6 [&_pre]:overflow-x-auto",
].join(" ");

export default async function StaticPageView({ page }: Props) {
  const [t, locale] = await Promise.all([
    getTranslations("staticPage"),
    getLocale(),
  ]);
  const title = localized(page.title, locale);
  const content = localized(page.content, locale);

  return (
    <article
      className="max-w-[1000px] container py-6"
      dir={locale === "ar" ? "rtl" : "ltr"}
      lang={locale}
    >
      <Breadcrumb
        list={[
          { label: t("home"), href: "/" },
          { label: title, href: "" },
        ]}
      />
      <h1 className="mt-4 mb-2 text-2xl md:text-3xl font-bold text-light">
        {title}
      </h1>
      {page.updated_at && (
        <p className="mb-6 text-sm text-gray">
          {t("lastUpdated", {
            date: new Intl.DateTimeFormat(locale, {
              dateStyle: "long",
            }).format(new Date(page.updated_at)),
          })}
        </p>
      )}
      {/* HTML is allow-list sanitized by the API (App\Support\HtmlSanitizer) before it reaches us. */}
      <div
        className={CONTENT_CLASSES}
        dangerouslySetInnerHTML={{ __html: content }}
      />
    </article>
  );
}
