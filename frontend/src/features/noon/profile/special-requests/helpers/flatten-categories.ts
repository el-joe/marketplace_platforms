import type { ICategoryNavTree } from "@/src/layout/noon/header/types/category-nav-tree.type";

/** Leaf categories only (the backend requires an assignable category). */
export function flattenLeafCategories(
  tree: ICategoryNavTree[],
  locale: "en" | "ar",
): { label: string; value: string }[] {
  const out: { label: string; value: string }[] = [];
  const walk = (nodes: ICategoryNavTree[]) =>
    nodes.forEach((n) => {
      if (n.children?.length) walk(n.children);
      else out.push({ label: n.name[locale] || n.name.en, value: n.id });
    });
  walk(tree);
  return out;
}
