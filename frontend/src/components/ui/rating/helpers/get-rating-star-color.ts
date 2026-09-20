export function getRatingStarColor(rating: number) {
  if (rating >= 4.5) return "#006300";
  if (rating >= 3.5) return "#05AF25";
  return "#F8B200";
}
