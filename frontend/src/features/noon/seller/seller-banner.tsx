"use client";

import React, { useState } from "react";
import { Share2, Check, PhoneCall } from "lucide-react";
import { Button } from "@/src/components/ui/button";

interface SellerBannerProps {
  storeName: string;
}

export default function SellerBanner({ storeName }: SellerBannerProps) {
  const [copied, setCopied] = useState(false);

  const handleShare = () => {
    if (typeof window !== "undefined") {
      navigator.clipboard.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  return (
    <div className="relative w-full">
      {/* Patterned Banner Background */}
      <div
        className="relative h-44 sm:h-52 w-full overflow-hidden bg-[#faf4dc] border-b border-gray-200"
        style={{
          backgroundImage: `radial-gradient(#e8ddbe 1px, transparent 1px), radial-gradient(#e8ddbe 1px, #faf4dc 1px)`,
          backgroundSize: `24px 24px`,
          backgroundPosition: `0 0, 12px 12px`,
        }}
      >
        {/* Subtle decorative Noon-style illustration watermark overlay */}
        <div className="absolute inset-0 opacity-15 pointer-events-none flex justify-around items-center">
          <svg
            className="w-full h-full text-[#c5b57d]"
            viewBox="0 0 800 200"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
          >
            <rect
              x="80"
              y="40"
              width="60"
              height="50"
              rx="6"
              stroke="currentColor"
              strokeWidth="2"
              strokeDasharray="4 4"
            />
            <rect
              x="260"
              y="80"
              width="50"
              height="40"
              rx="4"
              stroke="currentColor"
              strokeWidth="2"
              strokeDasharray="4 4"
            />
            <rect
              x="440"
              y="30"
              width="70"
              height="60"
              rx="6"
              stroke="currentColor"
              strokeWidth="2"
              strokeDasharray="4 4"
            />
            <rect
              x="620"
              y="70"
              width="55"
              height="45"
              rx="5"
              stroke="currentColor"
              strokeWidth="2"
              strokeDasharray="4 4"
            />
          </svg>
        </div>

        {/* Share Button (Top Right) */}
        <div className="container relative h-full flex justify-end items-start pt-4 sm:pt-6">
          <Button
            onClick={handleShare}
            variant="outline"
            size="sm"
            className="bg-white hover:bg-gray-50 text-[#3866df] border-gray-300 shadow-sm rounded-md px-3 py-1.5 text-xs font-semibold tracking-wider flex items-center gap-1.5 transition-all"
          >
            <span>{copied ? "COPIED" : "SHARE"}</span>
            {copied ? (
              <Check className="w-3.5 h-3.5 text-green-600" />
            ) : (
              <Share2 className="w-3.5 h-3.5" />
            )}
          </Button>
        </div>
      </div>

      {/* Overlapping Seller Logo */}
      <div className="container relative">
        <div className="absolute -bottom-12 sm:-bottom-14 left-4 sm:left-6 z-10">
          <div className="relative w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-[#fde000] border-4 border-white shadow-md flex flex-col items-center justify-center p-2 text-center select-none">
            {/* Phone/Bubble Badge Icon */}
            <div className="w-7 h-7 rounded-full bg-[#0066cc] flex items-center justify-center text-white mb-0.5 shadow-xs">
              <PhoneCall className="w-3.5 h-3.5" />
            </div>
            <span className="text-[13px] sm:text-sm font-black tracking-tight text-gray-900 leading-tight">
              CallMate
            </span>
            <span className="text-[9px] font-medium text-gray-700 tracking-tighter">
              – telecom –
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
