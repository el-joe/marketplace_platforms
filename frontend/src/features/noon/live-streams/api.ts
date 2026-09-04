import type { ApiEnvelope, LiveStreamCard, LiveStreamDetail, StreamComment } from './types';

const PUBLIC_BASE = process.env.NEXT_PUBLIC_API_PUBLIC_URL ?? '/api/public/v1';

async function fetchPublic<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${PUBLIC_BASE}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(init?.headers ?? {}),
    },
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

export async function getStreams(): Promise<LiveStreamCard[]> {
  const env = await fetchPublic<ApiEnvelope<LiveStreamCard[]>>('/streams');
  return env.data;
}

export async function getStream(id: string): Promise<LiveStreamDetail> {
  const env = await fetchPublic<ApiEnvelope<LiveStreamDetail>>(`/streams/${id}`);
  return env.data;
}

export async function postComment(
  streamId: string,
  body: string,
  guestName: string,
): Promise<StreamComment> {
  const env = await fetchPublic<ApiEnvelope<StreamComment>>(
    `/streams/${streamId}/comments`,
    { method: 'POST', body: JSON.stringify({ body, guest_name: guestName }) },
  );
  return env.data;
}

export async function postLike(streamId: string, guestToken: string): Promise<number> {
  const env = await fetchPublic<ApiEnvelope<{ likes_count: number }>>(
    `/streams/${streamId}/like`,
    { method: 'POST', body: JSON.stringify({ guest_token: guestToken }) },
  );
  return env.data.likes_count;
}

export async function postSignal(
  streamId: string,
  type: 'offer' | 'answer' | 'ice-candidate',
  payload: object,
  peerId: string,
): Promise<void> {
  await fetchPublic(`/streams/${streamId}/signal`, {
    method: 'POST',
    body:   JSON.stringify({ type, payload, peer_id: peerId }),
  });
}
