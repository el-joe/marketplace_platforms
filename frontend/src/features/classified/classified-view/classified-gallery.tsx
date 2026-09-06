"use client";

import React, { useState } from "react";
import Image from "next/image";
import { Rocket, Camera, ChevronLeft, ChevronRight, X } from "lucide-react";
import { ClassifiedImage } from "./types";

interface ClassifiedGalleryProps {
  images: ClassifiedImage[];
  promotedBadge?: string;
}

export default function ClassifiedGallery({
  images,
  promotedBadge = "Promoted Turbo",
}: ClassifiedGalleryProps) {
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [isLightboxOpen, setIsLightboxOpen] = useState(false);

  const mainImage = images[selectedIndex] || images[0];

  const handlePrev = (e: React.MouseEvent) => {
    e.stopPropagation();
    setSelectedIndex((prev) => (prev === 0 ? images.length - 1 : prev - 1));
  };

  const handleNext = (e: React.MouseEvent) => {
    e.stopPropagation();
    setSelectedIndex((prev) => (prev === images.length - 1 ? 0 : prev + 1));
  };

  return (
    <div className="w-full">
      {/* Gallery Grid: 1 large on left, 3 stacked on right */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-2 sm:gap-2.5 rounded-xl overflow-hidden">
        {/* Main Large Image (8 cols on desktop) */}
        <div
          onClick={() => setIsLightboxOpen(true)}
          className="relative md:col-span-8 aspect-[16/10] md:aspect-auto md:h-[400px] lg:h-[450px] cursor-pointer group overflow-hidden rounded-xl bg-neutral-900"
        >
          <Image
            src={mainImage.url}
            alt={mainImage.alt || "Classified vehicle image"}
            fill
            priority
            className="object-cover transition-transform duration-300 group-hover:scale-102 rounded-xl"
            sizes="   (max-width: 768px) 100vw, (max-width: 1200px) 66vw, 750px"
          />

          {/* Badges on Bottom Left */}
          <div className="absolute bottom-3 start-3 flex items-center gap-2 z-10">
            {promotedBadge && (
              <div className="bg-white/95 text-slate-800 text-xs font-semibold px-2.5 py-1 rounded shadow flex items-center gap-1.5 backdrop-blur-xs">
                <Rocket className="w-3.5 h-3.5 text-red-500 fill-red-500" />
                <span>{promotedBadge}</span>
              </div>
            )}
            <div className="bg-black/70 text-white text-xs font-medium px-2.5 py-1 rounded shadow flex items-center gap-1.5 backdrop-blur-xs">
              <Camera className="w-3.5 h-3.5" />
              <span>{images.length}</span>
            </div>
          </div>

          {/* Quick arrow controls on hover */}
          {images.length > 1 && (
            <div className="absolute inset-0 flex items-center justify-between px-3 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
              <button
                onClick={handlePrev}
                className="pointer-events-auto w-9 h-9 rounded-full bg-black/50 text-white flex items-center justify-center hover:bg-black/75 transition-colors"
                aria-label="Previous image"
              >
                <ChevronLeft className="w-5 h-5 rtl:rotate-180" />
              </button>
              <button
                onClick={handleNext}
                className="pointer-events-auto w-9 h-9 rounded-full bg-black/50 text-white flex items-center justify-center hover:bg-black/75 transition-colors"
                aria-label="Next image"
              >
                <ChevronRight className="w-5 h-5 rtl:rotate-180" />
              </button>
            </div>
          )}
        </div>

        {/* Desktop Side Thumbnails Stack (4 cols on desktop) */}
        <div className="hidden md:flex md:col-span-4 flex-col gap-2 h-[400px] lg:h-[450px]">
          {/* Thumb 1 */}
          {images[0] && (
            <div
              onClick={() => setSelectedIndex(0)}
              className={`relative flex-1 rounded-lg overflow-hidden cursor-pointer bg-neutral-900 border-2 transition-all ${
                selectedIndex === 0
                  ? "border-blue-600 ring-1 ring-blue-600"
                  : "border-transparent opacity-90 hover:opacity-100"
              }`}
            >
              <Image
                src={images[0].url}
                alt={images[0].alt || "Thumbnail 1"}
                fill
                className="object-cover"
                sizes="300px"
              />
            </div>
          )}

          {/* Thumb 2 */}
          {images[1] && (
            <div
              onClick={() => setSelectedIndex(1)}
              className={`relative flex-1 rounded-lg overflow-hidden cursor-pointer bg-neutral-900 border-2 transition-all ${
                selectedIndex === 1
                  ? "border-blue-600 ring-1 ring-blue-600"
                  : "border-transparent opacity-90 hover:opacity-100"
              }`}
            >
              <Image
                src={images[1].url}
                alt={images[1].alt || "Thumbnail 2"}
                fill
                className="object-cover"
                sizes="300px"
              />
            </div>
          )}

          {/* Thumb 3 with "Show More Photos" overlay */}
          {images[2] && (
            <div
              onClick={() => setIsLightboxOpen(true)}
              className="relative flex-1 rounded-lg overflow-hidden cursor-pointer bg-neutral-900 group"
            >
              <Image
                src={images[2].url}
                alt={images[2].alt || "Thumbnail 3"}
                fill
                className="object-cover group-hover:scale-105 transition-transform"
                sizes="300px"
              />
              {/* Overlay with Show More Photos */}
              <div className="absolute inset-0 bg-black/60 hover:bg-black/50 transition-colors flex items-center justify-center text-center p-2">
                <span className="text-white font-semibold text-sm sm:text-base tracking-wide drop-shadow">
                  Show More Photos
                </span>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Mobile thumbnails row */}
      <div className="flex md:hidden items-center gap-2 mt-2 overflow-x-auto pb-1 no-scrollbar">
        {images.map((img, idx) => (
          <button
            key={img.id || idx}
            onClick={() => setSelectedIndex(idx)}
            className={`relative w-20 h-14 shrink-0 rounded-md overflow-hidden border-2 transition-all ${
              selectedIndex === idx
                ? "border-blue-600 scale-102"
                : "border-transparent opacity-75"
            }`}
          >
            <Image
              src={img.url}
              alt={`thumbnail-${idx}`}
              fill
              className="object-cover"
            />
          </button>
        ))}
      </div>

      {/* Fullscreen Lightbox Modal */}
      {isLightboxOpen && (
        <div className="fixed inset-0 z-50 bg-black/95 backdrop-blur-sm flex flex-col items-center justify-center p-4">
          <button
            onClick={() => setIsLightboxOpen(false)}
            className="absolute top-4 end-4 text-white hover:text-gray-300 p-2 rounded-full bg-white/10 hover:bg-white/20 transition-colors"
            aria-label="Close lightbox"
          >
            <X className="w-6 h-6" />
          </button>

          <div className="relative w-full max-w-5xl h-[70vh] flex items-center justify-center">
            <Image
              src={images[selectedIndex]?.url || mainImage.url}
              alt="Expanded view"
              fill
              className="object-contain"
            />

            <button
              onClick={handlePrev}
              className="absolute start-2 w-11 h-11 rounded-full bg-black/60 hover:bg-black/80 text-white flex items-center justify-center transition-colors"
              aria-label="Previous image"
            >
              <ChevronLeft className="w-6 h-6 rtl:rotate-180" />
            </button>
            <button
              onClick={handleNext}
              className="absolute end-2 w-11 h-11 rounded-full bg-black/60 hover:bg-black/80 text-white flex items-center justify-center transition-colors"
              aria-label="Next image"
            >
              <ChevronRight className="w-6 h-6 rtl:rotate-180" />
            </button>
          </div>

          {/* Bottom thumbnails inside lightbox */}
          <div className="flex items-center gap-2 mt-4 max-w-4xl overflow-x-auto py-2 px-4 bg-black/40 rounded-xl">
            {images.map((img, idx) => (
              <button
                key={img.id || idx}
                onClick={() => setSelectedIndex(idx)}
                className={`relative w-16 h-12 shrink-0 rounded-md overflow-hidden border-2 transition-all ${
                  selectedIndex === idx
                    ? "border-blue-500 scale-105"
                    : "border-transparent opacity-60 hover:opacity-100"
                }`}
              >
                <Image
                  src={img.url}
                  alt={`thumb-${idx}`}
                  fill
                  className="object-cover"
                />
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
