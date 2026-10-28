import Echo from "laravel-echo";
import type { ChannelAuthorizationCallback } from "pusher-js";
import Pusher from "pusher-js";
import { getCookie } from "cookies-next";

declare global {
  interface Window {
    Pusher: typeof Pusher;
    Echo: Echo<"reverb">;
  }
}

/** Private-channel auth lives on the customer API root: `.../api/customer/broadcasting/auth`. */
const BROADCAST_AUTH_URL = `${(process.env.NEXT_PUBLIC_BASE_API_URL ?? "").replace(/\/v1\/?$/, "")}/broadcasting/auth`;

export function initEcho() {
  if (typeof window === "undefined" || window.Echo) return;

  window.Pusher = Pusher;

  window.Echo = new Echo({
    broadcaster: "reverb",
    key: process.env.NEXT_PUBLIC_REVERB_APP_KEY!,
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST!,
    wsPort: parseInt(process.env.NEXT_PUBLIC_REVERB_PORT ?? "443", 10),
    wssPort: parseInt(process.env.NEXT_PUBLIC_REVERB_PORT ?? "443", 10),
    forceTLS: (process.env.NEXT_PUBLIC_REVERB_SCHEME ?? "https") === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: BROADCAST_AUTH_URL,
    // Customers authenticate with a Bearer JWT (not a session cookie), so private
    // channels need a custom authorizer that reads the token at subscribe time.
    authorizer: (channel: { name: string }) => ({
      authorize: (
        socketId: string,
        callback: ChannelAuthorizationCallback,
      ) => {
        fetch(BROADCAST_AUTH_URL, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${getCookie("access_token") ?? ""}`,
          },
          body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
        })
          .then((res) => {
            if (!res.ok) throw new Error(`Broadcast auth failed (${res.status})`);
            return res.json();
          })
          .then((data) => callback(null, data))
          .catch((err: Error) => callback(err, null));
      },
    }),
  });
}
