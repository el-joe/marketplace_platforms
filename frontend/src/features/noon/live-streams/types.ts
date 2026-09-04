export type StreamStatus = 'scheduled' | 'live' | 'ended';

export interface LiveStreamCard {
  id: string;
  title: { en: string; ar: string };
  thumbnail_url: string | null;
  status: StreamStatus;
  scheduled_at: string | null;
  started_at: string | null;
  ended_at: string | null;
  total_viewers: number;
  likes_count: number;
}

export interface StreamComment {
  id: string;
  author: string;
  body: string;
  created_at: string;
}

export interface LiveStreamDetail extends LiveStreamCard {
  description: { en: string; ar: string };
  /** Only present when status === 'live' */
  stream_key: string | null;
  comments: StreamComment[];
}

export interface ApiEnvelope<T> {
  success: boolean;
  message?: string;
  data: T;
}
