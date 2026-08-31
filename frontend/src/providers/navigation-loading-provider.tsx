"use client";

import { createContext, useCallback, useContext, useEffect, useState } from "react";

interface INavigationLoadingContext {
  isLoading: boolean;
  startNavigationLoading: () => void;
}

const initialState: INavigationLoadingContext = {
  isLoading: false,
  startNavigationLoading: () => {},
};

const navigationLoadingContext =
  createContext<INavigationLoadingContext>(initialState);

const toAbsoluteURL = (url: string) => new URL(url, window.location.href).href;

const isSameHostname = (current: string, target: string) =>
  new URL(current).hostname.replace(/^www\./, "") ===
  new URL(target).hostname.replace(/^www\./, "");

const findClosestAnchor = (
  target: EventTarget | null,
): HTMLAnchorElement | null => {
  let node = target as HTMLElement | null;
  while (node && node.tagName?.toLowerCase() !== "a") {
    node = node.parentElement;
  }
  return node as HTMLAnchorElement | null;
};

export const NavigationLoadingProvider = ({
  children,
}: {
  children: React.ReactNode;
}) => {
  const [isLoading, setIsLoading] = useState(false);

  const startNavigationLoading = useCallback(() => setIsLoading(true), []);

  useEffect(() => {
    const stopNavigationLoading = () => setIsLoading(false);

    const handleClick = (e: MouseEvent) => {
      try {
        const anchor = findClosestAnchor(e.target);
        const href = anchor?.href;
        if (!href) return;

        const current = window.location.href;
        if (href === current) return;
        if (!isSameHostname(current, href)) return;
        if (anchor.target && anchor.target !== "_self") return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        if (!toAbsoluteURL(href).startsWith("http")) return;

        startNavigationLoading();
      } catch {
        // Malformed URL — let the browser handle it, no loading feedback needed.
      }
    };

    // Every Next.js client-side navigation (Link clicks and programmatic
    // router.push/replace alike) ends by committing the URL through the
    // History API, so patching it is the one reliable "navigation settled"
    // signal available without opting every page into dynamic rendering
    // via useSearchParams/usePathname at the root.
    const originalPushState = window.history.pushState.bind(window.history);
    const originalReplaceState = window.history.replaceState.bind(
      window.history,
    );

    window.history.pushState = (...args) => {
      stopNavigationLoading();
      return originalPushState(...args);
    };
    window.history.replaceState = (...args) => {
      stopNavigationLoading();
      return originalReplaceState(...args);
    };

    document.addEventListener("click", handleClick);
    window.addEventListener("popstate", stopNavigationLoading);
    window.addEventListener("pagehide", stopNavigationLoading);

    return () => {
      document.removeEventListener("click", handleClick);
      window.removeEventListener("popstate", stopNavigationLoading);
      window.removeEventListener("pagehide", stopNavigationLoading);
      window.history.pushState = originalPushState;
      window.history.replaceState = originalReplaceState;
    };
  }, [startNavigationLoading]);

  return (
    <navigationLoadingContext.Provider
      value={{ isLoading, startNavigationLoading }}
    >
      {children}
    </navigationLoadingContext.Provider>
  );
};

export const useNavigationLoadingContext = () =>
  useContext(navigationLoadingContext);
