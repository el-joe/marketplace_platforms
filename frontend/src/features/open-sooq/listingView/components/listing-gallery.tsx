"use client";

import { useState } from "react";
import Image from "next/image";
import type { ClassifiedImage } from "../helpers/types";

type Props = {
  images: ClassifiedImage[];
  title: string;
};

export default function ListingGallery({ images, title }: Props) {
  const [selected, setSelected] = useState(0);

  if (!images.length) {
    return (
      <div className="w-full h-80 bg-gray-2 rounded-2xl flex items-center justify-center text-gray-300 text-6xl">
        📷
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="relative w-full aspect-[4/3] bg-gray-2 rounded-2xl overflow-hidden">
        <Image
          src={images[selected].url}
          alt={title}
          fill
          className="object-contain"
          sizes="(max-width: 768px) 100vw, 600px"
          priority
        />
      </div>

      {images.length > 1 && (
        <div className="flex gap-2 overflow-x-auto pb-1">
          {images.map((img, i) => (
            <button
              key={img.id}
              onClick={() => setSelected(i)}
              className={`relative shrink-0 w-16 h-16 rounded-xl overflow-hidden border-2 transition-colors ${
                selected === i ? "border-blue" : "border-transparent"
              }`}
            >
              <Image
                src={img.url}
                alt={`${title} ${i + 1}`}
                fill
                className="object-cover"
                sizes="64px"
              />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
