export type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

export type DevicePlatform = "ios" | "android" | "web";

export type ActiveSession = {
  id: string;
  platform: DevicePlatform | string;
  last_used_at: string;
  token_masked: string;
};

export type ActiveSessionsResponse = {
  devices: ActiveSession[];
  session_count: number;
};
