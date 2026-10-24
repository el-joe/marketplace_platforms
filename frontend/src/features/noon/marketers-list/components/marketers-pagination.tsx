import { getTranslations } from "next-intl/server";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { buildListHref } from "../helpers/search-params";
import type { GenderParam, PaginationMeta } from "../helpers/types";

interface Props {
  meta: PaginationMeta;
  gender: GenderParam;
  type: string;
}

const base =
  "inline-flex h-10 w-10 items-center justify-center rounded-full border border-border-color text-primary hover:bg-gray-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary";

export default async function MarketersPagination({ meta, gender, type }: Props) {
  if (meta.last_page <= 1) return null;
  const t = await getTranslations("marketers");
  const { current_page: page, last_page: last } = meta;

  return (
    <nav aria-label={t("paginationLabel")} className="mt-10 flex items-center justify-center gap-4">
      {page > 1 ? (
        <Link href={buildListHref({ gender, type, page: page - 1 })} aria-label={t("previous")} rel="prev" className={base}>
          <ChevronLeft className="h-5 w-5 rtl:rotate-180" />
        </Link>
      ) : (
        <span aria-hidden className={`${base} opacity-40`}>
          <ChevronLeft className="h-5 w-5 rtl:rotate-180" />
        </span>
      )}
      <span className="text-sm font-semibold text-light">{t("pageOf", { page, last })}</span>
      {page < last ? (
        <Link href={buildListHref({ gender, type, page: page + 1 })} aria-label={t("next")} rel="next" className={base}>
          <ChevronRight className="h-5 w-5 rtl:rotate-180" />
        </Link>
      ) : (
        <span aria-hidden className={`${base} opacity-40`}>
          <ChevronRight className="h-5 w-5 rtl:rotate-180" />
        </span>
      )}
    </nav>
  );
}
