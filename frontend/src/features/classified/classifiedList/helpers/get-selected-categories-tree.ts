import { IClassifiedCategoriesList } from "./types";

export default function getSelectedCategoryTree(
  selectedCtg: string | null,
  categories: IClassifiedCategoriesList[],
): IClassifiedCategoriesList[] {
  if (!selectedCtg) return [];
  const parentCategory = categories.find(
    (cat) =>
      cat.id === selectedCtg ||
      cat.children.find((child) => child.id === selectedCtg),
  );
  if (!parentCategory) return [];
  const childCategory = parentCategory?.children?.find(
    (cat) => cat.id === selectedCtg,
  );
  return childCategory ? [parentCategory, childCategory] : [parentCategory];
}
