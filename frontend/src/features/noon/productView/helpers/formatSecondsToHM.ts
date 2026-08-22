export default function formatSecondsToHM(totalSeconds: number): string {
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);

  const hh = String(hours).padStart(2, "0");
  const mm = String(minutes).padStart(2, "0");

  if (hours) {
    return `${hh}h ${mm}m`;
  } else {
    return `${mm}m`;
  }
}
