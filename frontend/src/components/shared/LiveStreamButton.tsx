'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { getStreams } from '@/src/features/noon/live-streams/api';

export default function LiveStreamButton() {
  const [hasLive, setHasLive] = useState(false);

  useEffect(() => {
    const check = () =>
      getStreams()
        .then(streams => setHasLive(streams.some(s => s.status === 'live')))
        .catch(() => {});

    check();
    const interval = setInterval(check, 60_000);
    return () => clearInterval(interval);
  }, []);

  return (
    <Link
      href="/live-streams"
      className="fixed bottom-20 right-4 md:bottom-6 md:right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-full shadow-xl font-semibold text-sm transition-all hover:scale-105 active:scale-95"
      style={{ background: hasLive ? '#dc2626' : '#1f2937', color: '#fff' }}
    >
      {hasLive && <span className="w-2 h-2 rounded-full bg-white animate-pulse" />}
      <span className="text-base">📹</span>
      <span>{hasLive ? 'Live Now' : 'Streams'}</span>
    </Link>
  );
}
