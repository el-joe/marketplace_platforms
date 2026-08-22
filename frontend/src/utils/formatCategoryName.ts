const ID_SUFFIX_PATTERN = /-\d+$/;

export const formatCategoryName = (slugSegments: string[]) => {
  const lastSegment = slugSegments[slugSegments.length - 1] ?? "";

  return lastSegment
    .replace(ID_SUFFIX_PATTERN, "")
    .split("-")
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
};
