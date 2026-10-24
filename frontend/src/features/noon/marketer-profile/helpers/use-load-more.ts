"use client";
import { useCallback, useEffect, useRef, useState } from "react";
import { getMarketerListingsPage } from "../listings-api";
import type { MarketerFilters, MarketerListings } from "./types";

/**
 * Appends further pages (data.listings.meta) to the SSR first page.
 * The parent remounts this hook (via `key`) whenever the filters change.
 */
export function useLoadMore(slug: string, filters: MarketerFilters, initial: MarketerListings) {
  const [items, setItems] = useState(initial.items);
  const [meta, setMeta] = useState(initial.meta);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const busy = useRef(false);
  const sentinelRef = useRef<HTMLDivElement>(null);

  const hasMore = meta.current_page < meta.last_page;

  const loadMore = useCallback(async () => {
    if (busy.current || !hasMore) return;
    busy.current = true;
    setLoading(true);
    setFailed(false);
    try {
      const next = await getMarketerListingsPage(slug, filters, meta.current_page + 1);
      setItems((prev) => [...prev, ...next.items]);
      setMeta(next.meta);
    } catch {
      setFailed(true);
    } finally {
      busy.current = false;
      setLoading(false);
    }
  }, [slug, filters, meta.current_page, hasMore]);

  useEffect(() => {
    const el = sentinelRef.current;
    if (!el || !hasMore || failed) return;
    const io = new IntersectionObserver(
      ([e]) => {
        if (e.isIntersecting) void loadMore();
      },
      { rootMargin: "400px" },
    );
    io.observe(el);
    return () => io.disconnect();
  }, [hasMore, failed, loadMore]);

  return { items, meta, loading, failed, hasMore, loadMore, sentinelRef };
}
