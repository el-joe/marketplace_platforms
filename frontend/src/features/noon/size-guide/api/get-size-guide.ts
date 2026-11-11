import { fetchInstance } from "@/src/lib/utils";
import { SizeGuide } from "@/src/components/shared/size-guide/types";

/** Public vendor size map; null when it does not exist or is not live. */
export async function getSizeGuide(id: string): Promise<SizeGuide | null> {
  try {
    const { data } = await fetchInstance<{ data: SizeGuide }>(
      `/size-guides/${id}`,
      undefined,
      true,
    );
    return data ?? null;
  } catch {
    return null;
  }
}
