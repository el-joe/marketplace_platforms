export type StreamStatus = 'scheduled' | 'live' | 'ended';

export type MediaMode = 'audio' | 'video' | 'both';

export type StreamSource = 'live' | 'recorded';

/** Where a scheduled radio/video channel is right now (present while live, and as a replay once ended). */
export interface RecordedPlayback {
  url: string;
  type: 'audio' | 'video';
  duration_seconds: number;
  offset_seconds: number;
  server_time: string;
}

export interface LiveStreamCard {
  /** 'recorded' = scheduled audio/video file (radio channel); absent on older API versions */
  source?: StreamSource;
  media_type?: 'audio' | 'video' | null;
  duration_seconds?: number | null;
  scheduled_end_at?: string | null;
  id: string;
  title: { en: string; ar: string };
  thumbnail_url: string | null;
  status: StreamStatus;
  scheduled_at: string | null;
  started_at: string | null;
  ended_at: string | null;
  total_viewers: number;
  likes_count: number;
  /** null when the broadcaster has not published media yet */
  media_mode?: MediaMode | null;
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
  playback?: RecordedPlayback | null;
  comments: StreamComment[];
}

export interface ApiEnvelope<T> {
  success: boolean;
  message?: string;
  data: T;
}
