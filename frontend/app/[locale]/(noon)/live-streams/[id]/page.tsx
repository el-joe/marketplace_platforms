import StreamDetailFeature from '@/src/features/noon/live-streams/StreamDetailFeature';

type Props = { params: Promise<{ id: string }> };

export default async function StreamDetailPage({ params }: Props) {
  const { id } = await params;
  return <StreamDetailFeature streamId={id} />;
}
