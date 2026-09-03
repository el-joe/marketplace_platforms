"use client";

import React from "react";
import { MapPin, Mail, CircleHelp, Star } from "lucide-react";
import { ISellerProfile } from "./types";

interface SellerInfoSidebarProps {
  seller: ISellerProfile;
}

export default function SellerInfoSidebar({ seller }: SellerInfoSidebarProps) {
  return (
    <aside className="w-full lg:w-[340px] xl:w-[380px] shrink-0 pt-16 sm:pt-18 pb-8">
      {/* Seller Store Name */}
      <h1 className="text-2xl font-bold text-[#3866df] tracking-tight">
        {seller.store_name}
      </h1>

      {/* Address & Email */}
      <div className="mt-3 space-y-2 text-xs text-gray-600">
        <div className="flex items-start gap-2">
          <MapPin className="w-4 h-4 text-gray-400 shrink-0 mt-0.5" />
          <span className="leading-snug">{seller.address}</span>
        </div>
        <div className="flex items-center gap-2">
          <Mail className="w-4 h-4 text-gray-400 shrink-0" />
          <a
            href={`mailto:${seller.email}`}
            className="hover:text-primary transition-colors"
          >
            {seller.email}
          </a>
        </div>
      </div>

      {/* Seller Rating & Customers Card */}
      <div className="mt-6 rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
        <div className="grid grid-cols-2 divide-x divide-gray-200 rtl:divide-x-reverse">
          {/* Seller Rating */}
          <div className="pe-4">
            <div className="flex items-center justify-between text-xs text-gray-700 font-medium mb-1">
              <span>Seller Rating</span>
              <CircleHelp className="w-3.5 h-3.5 text-gray-400" />
            </div>
            <div className="flex items-center gap-1">
              <span className="text-2xl sm:text-3xl font-bold text-gray-900">
                {seller.seller_rating.toFixed(1)}
              </span>
              <Star className="w-4 h-4 fill-[#006300] stroke-[#006300]" />
            </div>
            <p className="text-[11px] text-gray-500 mt-1">
              {seller.positive_ratings_pct}% Positive Ratings
            </p>
          </div>

          {/* Customers */}
          <div className="ps-4">
            <div className="text-xs text-gray-700 font-medium mb-1">
              Customers
            </div>
            <div className="text-2xl sm:text-3xl font-bold text-gray-900">
              {seller.customers_count}
            </div>
            <p className="text-[11px] text-gray-500 mt-1">
              {seller.customers_period_text}
            </p>
          </div>
        </div>
      </div>

      {/* Product as Described Box */}
      <div className="mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
        <div className="flex items-center justify-between gap-3">
          <span className="text-xs sm:text-sm font-medium text-gray-800 whitespace-nowrap">
            Product as Described
          </span>
          <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
            <div
              className="h-full bg-[#75a72d] rounded-full transition-all duration-500"
              style={{ width: `${seller.product_as_described_pct}%` }}
            />
          </div>
          <span className="text-xs sm:text-sm font-bold text-[#006300] whitespace-nowrap">
            {seller.product_as_described_pct}%
          </span>
        </div>

        <div className="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
          <button
            type="button"
            className="flex items-center justify-between w-full hover:text-gray-700 transition-colors"
          >
            <span>What do these mean?</span>
            <CircleHelp className="w-3.5 h-3.5 text-gray-400" />
          </button>
        </div>
      </div>

      {/* Seller Since Section */}
      <div className="mt-6">
        <p className="text-xs text-gray-500">Seller Since</p>
        <p className="text-sm font-bold text-gray-900 mt-0.5">
          {seller.seller_since}
        </p>
      </div>
    </aside>
  );
}
