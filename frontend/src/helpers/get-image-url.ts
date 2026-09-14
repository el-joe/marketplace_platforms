export const getImageURL = (url: string): string => {
  if (!url) return "/images/no-image-available-icon.jpg";
  // Accept both https and http — backend serves http behind a reverse proxy on some envs.
  if (url.startsWith("http://") || url.startsWith("https://")) return url;
  // Relative path — prefix with storage base if env var is set, otherwise fallback.
  if (url.startsWith("/storage/") || url.startsWith("storage/")) {
    const base = process.env.NEXT_PUBLIC_STORAGE_URL ?? "";
    return base ? `${base.replace(/\/$/, "")}/${url.replace(/^\//, "")}` : url;
  }
  return "/images/no-image-available-icon.jpg";
};
