"use client";

import { useAuthContext } from "@/src/providers/auth-provider";

/** DOM id of the "Ask the lister" message input; the sidebar "Chat" button focuses it. */
export const INQUIRY_INPUT_ID = "classified-inquiry-input";

/**
 * Contact actions of the classified sidebar. Classified advertisers are reached through the inquiry box (there is no
 * separate chat thread for ads), so "Chat" brings the buyer to that box; guests are asked to sign in first because the
 * inquiry endpoint requires an authenticated customer.
 */
export function useContactActions() {
  const { isLogged, setAuthDialogIsOpen } = useAuthContext();

  const openChat = () => {
    if (!isLogged) {
      setAuthDialogIsOpen(true);
      return;
    }
    const input = document.getElementById(INQUIRY_INPUT_ID);
    if (input instanceof HTMLInputElement) {
      input.scrollIntoView({ behavior: "smooth", block: "center" });
      input.focus({ preventScroll: true });
    }
  };

  return { openChat };
}
