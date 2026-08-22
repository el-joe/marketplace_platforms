const starsColors = [
  { id: 1, stars: 5, color: "#006300" },
  { id: 2, stars: 4, color: "#006300" },
  { id: 3, stars: 3, color: "#05AF25" },
  { id: 4, stars: 2, color: "#F8B200" },
  { id: 5, stars: 1, color: "#F36302" },
];

export default function getStarSColorByCount(count: number): string {
  return (
    starsColors.find((color) => color.stars === count)?.color ??
    starsColors[0].color
  );
}
