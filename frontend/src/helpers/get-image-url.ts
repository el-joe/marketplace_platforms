export const getImageURL = (url: string | null | undefined): string => {
  if (!url) return "/images/no-image-available-icon.jpg";
  // Accept both https and http — backend serves http behind a reverse proxy on some envs.
  if (url.startsWith("http://") || url.startsWith("https://")) return url;
  // Local/public assets (e.g. "/images/...") — serve as-is.
  if (url.startsWith("/images/")) return url;
  // Any other relative path (with or without a leading slash, "/storage/..."
  // or a bare storage path) — prefix with the storage base when configured.
  const base = process.env.NEXT_PUBLIC_STORAGE_URL ?? "";
  if (base) return `${base.replace(/\/$/, "")}/${url.replace(/^\//, "")}`;
  // No storage base configured — only serve it if it's already an absolute-
  // looking path, otherwise fall back to the placeholder.
  return url.startsWith("/") ? url : "/images/no-image-available-icon.jpg";
};
