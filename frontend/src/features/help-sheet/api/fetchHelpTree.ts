import { helpTree } from "./mockData";
import type { HelpNode } from "../types";

const MOCK_LATENCY_MS = 900;

export function fetchHelpTree(): Promise<HelpNode> {
  return new Promise((resolve) => {
    setTimeout(() => resolve(helpTree), MOCK_LATENCY_MS);
  });
}
