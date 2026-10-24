'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { getStreams } from '@/src/features/noon/live-streams/api';

export default function LiveStreamButton() {
  const [hasLive, setHasLive] = useState(false);
  const [radioOnly, setRadioOnly] = useState(false);

  useEffect(() => {
    const check = () =>
      getStreams()
        .then(streams => {
          const live = streams.filter(s => s.status === 'live');
          setHasLive(live.length > 0);
          setRadioOnly(live.length > 0 && live.every(s => s.source === 'recorded'));
        })
        .catch(() => {});

    check();
    const interval = setInterval(check, 30_000);
    return () => clearInterval(interval);
  }, []);

  return (
    <Link
      href="/live-streams"
      className="fixed bottom-20 end-4 md:bottom-6 md:end-6 z-50 flex items-center gap-2 px-4 py-3 rounded-full shadow-xl font-semibold text-sm transition-all hover:scale-105 active:scale-95"
      style={{ background: hasLive ? '#dc2626' : '#1f2937', color: '#fff' }}
    >
      {hasLive && <span className="w-2 h-2 rounded-full bg-white animate-pulse" />}
      <span className="text-base">{radioOnly ? '📻' : '📹'}</span>
      <span>{hasLive ? (radioOnly ? 'On Air' : 'Live Now') : 'Streams'}</span>
    </Link>
  );
}
