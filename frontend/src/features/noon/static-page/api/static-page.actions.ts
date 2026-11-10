import { cache } from "react";
import { ApiRequestError, fetchInstance } from "@/src/lib/utils";
import type { ApiEnvelope, StaticPage } from "../helpers/types";

/**
 * Feature-only: GET /static-pages/{slug}. Returns `undefined` when the page
 * doesn't exist, is inactive or isn't published for the storefront, so the
 * route can render a 404. Wrapped in React `cache` so `generateMetadata` and
 * the page share one request per render.
 */
export const getStaticPage = cache(
  async (slug: string): Promise<StaticPage | undefined> => {
    try {
      const envelope = await fetchInstance<ApiEnvelope<StaticPage>>(
        `/static-pages/${encodeURIComponent(slug)}`,
      );
      return envelope.data;
    } catch (error) {
      if (error instanceof ApiRequestError && error.status === 404) {
        return undefined;
      }
      throw error;
    }
  },
);
