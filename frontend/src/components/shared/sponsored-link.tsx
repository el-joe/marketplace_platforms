"use client";

import React, { useRef } from "react";
import { Link } from "@/i18n/navigation";
import useLocale from "@/src/hooks/use-locale";
import { PaidAdMeta } from "@/src/components/shared/page-builder/types";
import { AdBadge } from "@/src/components/shared/ad-badge";
import { useAdImpression } from "@/src/hooks/use-ad-impression";
import { trackAdClick } from "@/src/services/ads";
import { cn } from "@/src/lib/utils";

type Props = {
  href: string | null | undefined;
  isExternal?: boolean;
  ad?: PaidAdMeta | null;
  isPaid?: boolean;
  className?: string;
  style?: React.CSSProperties;
  children: React.ReactNode;
};

const isAbsoluteUrl = (url: string) => /^https?:\/\//i.test(url);

export const SponsoredLink = ({ href, isExternal, ad, isPaid, className, style, children }: Props) => {
  const locale = useLocale();
  const containerRef = useRef<HTMLDivElement>(null);
  useAdImpression(containerRef, ad);

  const url = href || "#";
  const external = isExternal || isAbsoluteUrl(url);
  const showBadge = !!(isPaid || ad);
  const badgeLabel = ad ? ad.advertiser_label?.[locale] ?? ad.advertiser_label?.en ?? undefined : undefined;

  const handleClick = () => {
    if (ad) trackAdClick(ad);
  };

  return (
    <div ref={containerRef} className={cn("relative", className)} style={style}>
      {external ? (
        <a
          href={url}
          target="_blank"
          rel="sponsored noopener noreferrer"
          onClick={handleClick}
          className="block w-full h-full"
        >
          {children}
        </a>
      ) : (
        <Link
          href={url}
          rel={ad ? "sponsored" : undefined}
          onClick={handleClick}
          className="block w-full h-full"
        >
          {children}
        </Link>
      )}
      {showBadge && <AdBadge label={badgeLabel} />}
    </div>
  );
};
