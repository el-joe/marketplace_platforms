"use client";

import Image from "next/image";
import { Link } from "@/i18n/navigation";

type Props = {
  id: string;
  image_url: string;
  cta_url: string | null;
};

export default function AnnouncementBanner({ image_url, cta_url }: Props) {
  if (!image_url) return null;

  const inner = (
    <div className="relative w-full h-12 md:h-14">
      <Image
        src={image_url}
        alt="Announcement"
        fill
        className="object-cover object-center"
        sizes="100vw"
        priority
      />
    </div>
  );

  return (
    <div className="w-full">
      {cta_url ? (
        <Link href={cta_url} className="block w-full">
          {inner}
        </Link>
      ) : (
        inner
      )}
    </div>
  );
}
