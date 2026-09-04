import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: typeof Pusher;
    Echo: Echo<'reverb'>;
  }
}

export function initEcho() {
  if (typeof window === 'undefined' || window.Echo) return;

  window.Pusher = Pusher;

  window.Echo = new Echo({
    broadcaster: 'reverb',
    key: process.env.NEXT_PUBLIC_REVERB_APP_KEY!,
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST!,
    wsPort: parseInt(process.env.NEXT_PUBLIC_REVERB_PORT ?? '443', 10),
    wssPort: parseInt(process.env.NEXT_PUBLIC_REVERB_PORT ?? '443', 10),
    forceTLS: (process.env.NEXT_PUBLIC_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
  });
}