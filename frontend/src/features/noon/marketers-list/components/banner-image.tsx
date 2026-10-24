import Image from "next/image";
import { getImageURL } from "@/src/helpers/get-image-url";
import type { MarketerBanner } from "../helpers/types";

interface Props {
  banner: MarketerBanner;
  sizes: string;
  priority?: boolean;
}

/** Responsive banner art: desktop image from md up, mobile image below; text fallback if no art. */
export default function BannerImage({ banner, sizes, priority }: Props) {
  const alt = banner.title ?? "";
  if (!banner.image_desktop && !banner.image_mobile) {
    return (
      <div className="flex h-full w-full flex-col items-center justify-center gap-1 bg-gradient-to-br from-neutral-800 to-neutral-950 p-4 text-center text-white">
        <p className="text-lg font-black sm:text-2xl">{banner.title}</p>
        {banner.subtitle && <p className="text-xs text-white/70 sm:text-sm">{banner.subtitle}</p>}
      </div>
    );
  }
  const mobile = banner.image_mobile ?? banner.image_desktop;
  const desktop = banner.image_desktop ?? banner.image_mobile;
  return (
    <>
      <Image
        src={getImageURL(mobile)}
        alt={alt}
        fill
        sizes={sizes}
        priority={priority}
        className="object-cover md:hidden"
      />
      <Image
        src={getImageURL(desktop)}
        alt={alt}
        fill
        sizes={sizes}
        priority={priority}
        className="hidden object-cover md:block"
      />
    </>
  );
}
