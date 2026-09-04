'use client';

import { useEffect, useState } from 'react';
import { useLocale } from 'next-intl';
import Link from 'next/link';
import Image from 'next/image';
import { getStreams } from './api';
import type { LiveStreamCard, StreamStatus } from './types';

const STATUS_CONFIG: Record<StreamStatus, { label: string; badge: string }> = {
  live:      { label: 'LIVE',      badge: 'bg-red-600 text-white' },
  scheduled: { label: 'Scheduled', badge: 'bg-yellow-100 text-yellow-800' },
  ended:     { label: 'Ended',     badge: 'bg-gray-100 text-gray-600' },
};

function StreamCard({ stream, locale }: { stream: LiveStreamCard; locale: string }) {
  const title = stream.title[locale as 'en' | 'ar'] || stream.title.en;
  const cfg   = STATUS_CONFIG[stream.status];

  return (
    <Link
      href={`/live-streams/${stream.id}`}
      className="group bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow"
    >
      <div className="relative aspect-video bg-gray-100">
        {stream.thumbnail_url ? (
          <Image
            src={stream.thumbnail_url}
            alt={title}
            fill
            className="object-cover group-hover:scale-105 transition-transform duration-300"
            sizes="(max-width:640px)100vw,400px"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-5xl">📹</div>
        )}

        <span className={`absolute top-2 left-2 text-xs font-bold px-2.5 py-1 rounded-full ${cfg.badge} flex items-center gap-1.5`}>
          {stream.status === 'live' && (
            <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse" />
          )}
          {cfg.label}
        </span>

        {stream.status === 'live' && (
          <span className="absolute top-2 right-2 bg-black/60 text-white text-xs px-2 py-0.5 rounded-full">
            👁 {stream.total_viewers.toLocaleString()}
          </span>
        )}
      </div>

      <div className="p-4">
        <h3 className="font-semibold text-gray-900 line-clamp-2">{title}</h3>
        <div className="flex items-center gap-3 mt-2 text-xs text-gray-500">
          <span>❤️ {stream.likes_count.toLocaleString()}</span>
          {stream.scheduled_at && stream.status === 'scheduled' && (
            <span>🗓 {new Date(stream.scheduled_at).toLocaleString()}</span>
          )}
          {stream.ended_at && stream.status === 'ended' && (
            <span>Ended {new Date(stream.ended_at).toLocaleDateString()}</span>
          )}
        </div>
      </div>
    </Link>
  );
}

export default function StreamsListFeature() {
  const locale  = useLocale();
  const [streams, setStreams] = useState<LiveStreamCard[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getStreams().then(setStreams).catch(console.error).finally(() => setLoading(false));
  }, []);

  const live      = streams.filter(s => s.status === 'live');
  const scheduled = streams.filter(s => s.status === 'scheduled');
  const ended     = streams.filter(s => s.status === 'ended');

  if (loading) return (
    <div className="container py-8">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <div key={i} className="bg-gray-100 rounded-2xl aspect-video animate-pulse" />
        ))}
      </div>
    </div>
  );

  return (
    <div className="container py-8 space-y-10">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Live Streams</h1>
        <p className="text-gray-500 mt-1 text-sm">Watch live, upcoming, and past broadcasts</p>
      </div>

      {live.length > 0 && (
        <section>
          <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <span className="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse" /> Live Now
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {live.map(s => <StreamCard key={s.id} stream={s} locale={locale} />)}
          </div>
        </section>
      )}

      {scheduled.length > 0 && (
        <section>
          <h2 className="text-lg font-bold text-gray-900 mb-4">🗓 Upcoming</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {scheduled.map(s => <StreamCard key={s.id} stream={s} locale={locale} />)}
          </div>
        </section>
      )}

      {ended.length > 0 && (
        <section>
          <h2 className="text-lg font-bold text-gray-900 mb-4">📼 Past Streams</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {ended.map(s => <StreamCard key={s.id} stream={s} locale={locale} />)}
          </div>
        </section>
      )}

      {streams.length === 0 && (
        <div className="text-center py-24 text-gray-400">
          <div className="text-6xl mb-4">📹</div>
          <p className="text-lg font-medium">No streams yet</p>
          <p className="text-sm mt-1">Check back soon for upcoming live broadcasts</p>
        </div>
      )}
    </div>
  );
}
