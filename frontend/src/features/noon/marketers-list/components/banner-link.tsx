"use client";

import type { ReactNode } from "react";
import { Link } from "@/i18n/navigation";
import { trackBannerClick } from "../helpers/track-banner-click";

interface Props {
  href: string | null;
  clickUrl: string | null;
  className?: string;
  ariaLabel?: string;
  children: ReactNode;
}

/** Leaf: opens the banner link (new tab if external) and fires the click-tracking POST. */
export default function BannerLink({ href, clickUrl, className, ariaLabel, children }: Props) {
  if (!href) return <div className={className}>{children}</div>;

  const onClick = () => trackBannerClick(clickUrl);

  if (/^https?:\/\//.test(href)) {
    return (
      <a
        href={href}
        target="_blank"
        rel="noopener noreferrer"
        onClick={onClick}
        aria-label={ariaLabel}
        className={className}
      >
        {children}
      </a>
    );
  }

  return (
    <Link href={href} onClick={onClick} aria-label={ariaLabel} className={className}>
      {children}
    </Link>
  );
}
