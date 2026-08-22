"use client";

import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
import { cn } from "@/src/lib/utils";
import { buttonVariants } from "@/src/components/ui/button";

type Props = {
  currentPage: number;
  totalPages: number;
};

export default function PackagesPagination({ currentPage, totalPages }: Props) {
  const t = useTranslations("flights.pagination");
  const searchParams = useSearchParams();

  if (totalPages <= 1) return null;

  function buildUrl(page: number) {
    const params = new URLSearchParams(searchParams.toString());
    params.set("page", String(page));
    return `?${params.toString()}`;
  }

  const pages = Array.from({ length: totalPages }, (_, i) => i + 1);

  return (
    <nav
      aria-label={t("ariaLabel")}
      className="flex items-center justify-center gap-1.5"
    >
      <Link
        href={buildUrl(currentPage - 1)}
        aria-disabled={currentPage === 1}
        className={cn(
          buttonVariants({ size: "icon-sm", variant: "outline" }),
          currentPage === 1 && "pointer-events-none opacity-40",
        )}
      >
        <ChevronLeftIcon className="size-4" />
      </Link>

      {pages.map((page) => (
        <Link
          key={page}
          href={buildUrl(page)}
          className={cn(
            buttonVariants({ size: "icon-sm" }),
            page === currentPage
              ? "bg-blue-3 text-white border-transparent"
              : "border-border bg-transparent text-primary hover:bg-muted",
          )}
        >
          {page}
        </Link>
      ))}

      <Link
        href={buildUrl(currentPage + 1)}
        aria-disabled={currentPage === totalPages}
        className={cn(
          buttonVariants({ size: "icon-sm", variant: "outline" }),
          currentPage === totalPages && "pointer-events-none opacity-40",
        )}
      >
        <ChevronRightIcon className="size-4" />
      </Link>
    </nav>
  );
}
