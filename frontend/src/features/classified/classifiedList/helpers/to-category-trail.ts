import { IClassifiedBrowseCategory, IClassifiedCategoryRef } from "./types";

/** Breadcrumb trail (section > sub-category) of a resolved category. */
export default function toCategoryTrail(
  category?: IClassifiedBrowseCategory | null,
): IClassifiedCategoryRef[] {
  if (!category) return [];
  return category.parent
    ? [
        category.parent,
        { id: category.id, slug: category.slug, name: category.name },
      ]
    : [{ id: category.id, slug: category.slug, name: category.name }];
}
