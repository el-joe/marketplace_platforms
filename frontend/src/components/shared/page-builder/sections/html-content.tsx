"use client";
import { Block } from "../types";
import useLocale from "@/src/hooks/use-locale";

const ALIGN_CLASS: Record<string, string> = {
  left: "text-left",
  center: "text-center",
  right: "text-right",
  justify: "text-justify",
};

export const HtmlContent = ({ data }: { data: Block }) => {
  const locale = useLocale();
  const html =
    locale === "ar"
      ? data.config?.content_html_ar
      : data.config?.content_html_en;

  if (!html) return null;

  const textAlign = data.config?.text_align || "left";
  const maxWidth = data.config?.max_width || undefined;

  return (
    <div className="container">
      <div
        className={`prose max-w-none ${ALIGN_CLASS[textAlign] || ALIGN_CLASS.left}`}
        style={maxWidth ? { maxWidth, marginInline: "auto" } : undefined}
        dangerouslySetInnerHTML={{ __html: html }}
      />
    </div>
  );
};
