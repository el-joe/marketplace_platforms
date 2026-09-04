import { Suspense } from 'react';
import StreamsListFeature from '@/src/features/noon/live-streams/StreamsListFeature';

export const metadata = { title: 'Live Streams' };

export default function LiveStreamsPage() {
  return (
    <Suspense>
      <StreamsListFeature />
    </Suspense>
  );
}
