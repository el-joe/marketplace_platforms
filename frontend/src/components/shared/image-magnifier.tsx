"use client";

import Image from "next/image";
import { MouseEvent, useState } from "react";

// import { useState, MouseEvent } from 'react';
// import Image from 'next/image';

interface MagnifierProps {
  src: string;
  alt: string;
  width: number;
  height: number;
  zoomLevel?: number;
  magnifierSize?: number;
}

export default function ImageMagnifier({
  src,
  alt,
  width,
  height,
  zoomLevel = 2.5,
}: MagnifierProps) {
  const [position, setPosition] = useState({ x: 0, y: 0 });
  const [showMagnifier, setShowMagnifier] = useState(false);

  const handleMouseMove = (e: MouseEvent<HTMLDivElement>) => {
    const {
      left,
      top,
      width: elWidth,
      height: elHeight,
    } = e.currentTarget.getBoundingClientRect();

    // Calculate percentages (0% to 100%) instead of pixels
    const x = ((e.clientX - left) / elWidth) * 100;
    const y = ((e.clientY - top) / elHeight) * 100;

    setPosition({ x, y });
  };

  return (
    <div
      onMouseEnter={() => setShowMagnifier(true)}
      onMouseLeave={() => setShowMagnifier(false)}
      onMouseMove={handleMouseMove}
      className="cursor-zoom-in"
    >
      <Image
        src={src}
        alt={alt}
        width={width}
        height={height}
        className="mx-auto h-full object-contain transition"
        style={{
          scale: showMagnifier ? zoomLevel : 1,
          transformOrigin: `${position.x}% ${position.y}%`,
        }}
      />
    </div>
  );
}
