'use client';

import { useTranslations } from 'next-intl';
import { Link, usePathname } from '@/i18n/navigation';

const NAWY_NOW_PATH = '/nawy-now';
const BOLT = "\u26A1";

// Fixed shortcut stacked above LiveStreamButton (bottom-20 / md:bottom-6, ~48px tall).
export default function NawyNowButton() {
  const t = useTranslations('nawyNow');
  const pathname = usePathname();

  if (pathname === NAWY_NOW_PATH || pathname.startsWith(`${NAWY_NOW_PATH}/`)) return null;

  return (
    <Link
      href={NAWY_NOW_PATH}
      aria-label={t('ariaLabel')}
      className="fixed bottom-36 end-4 md:bottom-24 md:end-6 z-40 flex items-center gap-2 px-4 py-3 rounded-full shadow-xl font-semibold text-sm bg-amber-500 text-white transition-all hover:scale-105 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-amber-600"
    >
      <span aria-hidden="true" className="text-base">{BOLT}</span>
      <span>{t('label')}</span>
    </Link>
  );
}
