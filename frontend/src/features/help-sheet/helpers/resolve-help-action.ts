import type { HelpNode } from "../types";

export function findNodeById(
  root: HelpNode,
  id: string,
): HelpNode | undefined {
  if (root.id === id) return root;
  for (const child of root.children ?? []) {
    const found = findNodeById(child, id);
    if (found) return found;
  }
  return undefined;
}
