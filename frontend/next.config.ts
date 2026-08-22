import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const nextConfig: NextConfig = {
  /* config options here */
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "f.nooncdn.com",
      },
      {
        protocol: "https",
        hostname: "a.nooncdn.com",
      },
      {
        protocol: "https",
        hostname: "opensooqui2.os-cdn.com",
      },
      {
        protocol: "https",
        hostname: "eg.opensooq.com",
      },
      {
        protocol: "https",
        hostname: "opensooq-images.os-cdn.com",
      },
      {
        protocol: "https",
        hostname: "noon.codefanz.com",
      },
      {
        protocol: "https",
        hostname: "images.unsplash.com",
      },
      { protocol: "https", hostname: "api.noon.codefanz.com" },
    ],
  },
};

const withNextIntl = createNextIntlPlugin();
export default withNextIntl(nextConfig);
