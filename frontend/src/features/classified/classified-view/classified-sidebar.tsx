"use client";

import React, { useState } from "react";
import Link from "next/link";
import { useTranslations } from "next-intl";
import {
  Phone,
  MessageCircle,
  User,
  Star,
  ChevronRight,
  Plus,
  Check,
  Camera,
  Flame,
  ArrowRight,
} from "lucide-react";
import { ClassifiedSeller } from "./types";

interface ClassifiedSidebarProps {
  seller: ClassifiedSeller;
  onOpenChat?: () => void;
}

export default function ClassifiedSidebar({
  seller,
  onOpenChat,
}: ClassifiedSidebarProps) {
  const [phoneRevealed, setPhoneRevealed] = useState(false);
  const [isFollowing, setIsFollowing] = useState(false);
  const t = useTranslations("classifiedSidebar");

  return (
    <aside className="w-full flex flex-col gap-4">
      {/* 1. Primary Action Buttons (Call / Chat) */}
      <div className="flex flex-col gap-2.5">
        {/* Phone Call / Reveal Button */}
        <button
          onClick={() => setPhoneRevealed(true)}
          className="w-full bg-[#0070f3] hover:bg-blue-600 active:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl shadow-xs flex items-center justify-center gap-2.5 text-base sm:text-lg transition-colors cursor-pointer"
        >
          <Phone className="w-5 h-5 fill-white text-white" />
          <span>{phoneRevealed ? seller.phone : seller.phoneMasked}</span>
        </button>

        {/* Chat Button */}
        <button
          onClick={onOpenChat}
          className="w-full bg-white hover:bg-gray-50 active:bg-gray-100 text-gray-900 font-bold py-2.5 px-4 rounded-xl border border-gray-200 shadow-xs flex items-center justify-center gap-2.5 text-sm sm:text-base transition-colors cursor-pointer"
        >
          <MessageCircle className="w-5 h-5 text-emerald-600 fill-emerald-100" />
          <span>{t("chat")}</span>
        </button>
      </div>

      {/* 2. Seller Profile Card */}
      <div className="w-full bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
        <div className="flex items-start justify-between gap-3 mb-3">
          <div className="flex items-center gap-3">
            {/* Avatar */}
            <div className="w-12 h-12 rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center overflow-hidden shrink-0">
              {seller.avatar ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={seller.avatar}
                  alt={seller.name}
                  className="w-full h-full object-cover"
                />
              ) : (
                <User className="w-7 h-7 text-gray-400" />
              )}
            </div>

            {/* Seller Name & Follow */}
            <div>
              <h3 className="font-bold text-gray-900 text-sm sm:text-base leading-tight">
                {seller.name}
              </h3>
              <button
                onClick={() => setIsFollowing(!isFollowing)}
                className="mt-1 text-xs font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-0.5"
              >
                {isFollowing ? (
                  <>
                    <Check className="w-3 h-3 text-emerald-600" />
                    <span className="text-emerald-700">{t("following")}</span>
                  </>
                ) : (
                  <>
                    <Plus className="w-3 h-3" />
                    <span>{t("follow")}</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </div>

        {/* Rating & Member Since Container */}
        <div className="bg-gray-50 rounded-lg p-2.5 mb-3 grid grid-cols-2 gap-2 text-xs">
          <div>
            <span className="text-gray-500 block text-[11px]">{t("rating")}</span>
            <div className="flex items-center gap-1 mt-0.5 font-medium text-gray-800">
              <span className="font-bold">{seller.rating}</span>
              <div className="flex items-center text-gray-300">
                {[...Array(5)].map((_, i) => (
                  <Star
                    key={i}
                    className={`w-3 h-3 ${
                      i < seller.rating
                        ? "fill-yellow-400 text-yellow-400"
                        : "fill-gray-200 text-gray-300"
                    }`}
                  />
                ))}
              </div>
              <span className="text-gray-400">({seller.reviewsCount})</span>
            </div>
          </div>

          <div>
            <span className="text-gray-500 block text-[11px]">
              {t("memberSince")}
            </span>
            <span className="font-bold text-gray-800 block mt-0.5">
              {seller.memberSince}
            </span>
          </div>
        </div>

        {/* View All Listings link — only routable for vendor sellers today;
            individual sellers have no public seller-profile page yet. */}
        {seller.vendorId ? (
          <Link
            href={`/seller/${seller.vendorId}`}
            className="text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 hover:underline"
          >
            <span>{t("viewAllListings", { count: seller.totalListings })}</span>
            <ChevronRight className="w-3.5 h-3.5 rtl:rotate-180" />
          </Link>
        ) : (
          <a
            href="#seller-listings"
            className="text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 hover:underline"
          >
            <span>{t("viewAllListings", { count: seller.totalListings })}</span>
            <ChevronRight className="w-3.5 h-3.5 rtl:rotate-180" />
          </a>
        )}
      </div>

      {/* 3. General Safety Tips Card */}
      <div className="w-full bg-sky-50/70 border border-sky-100 rounded-xl p-4 text-xs sm:text-sm">
        <h4 className="font-bold text-gray-900 mb-2.5">{t("generalTips")}</h4>
        <ul className="space-y-1.5 text-gray-700 text-xs sm:text-[13px]">
          <li className="flex items-start gap-2">
            <span className="w-1.5 h-1.5 rounded-full bg-gray-500 mt-1.5 shrink-0" />
            <span>{t("tipPublicPlaces")}</span>
          </li>
          <li className="flex items-start gap-2">
            <span className="w-1.5 h-1.5 rounded-full bg-gray-500 mt-1.5 shrink-0" />
            <span>{t("tipNoAdvancePayment")}</span>
          </li>
          <li className="flex items-start gap-2">
            <span className="w-1.5 h-1.5 rounded-full bg-gray-500 mt-1.5 shrink-0" />
            <span>{t("tipInspectProduct")}</span>
          </li>
        </ul>
      </div>

      {/* 4. Promo Card 1: Sell Anything */}
      <div className="w-full bg-white border border-gray-200 rounded-xl p-4 shadow-xs flex items-center gap-3.5">
        <div className="w-11 h-11 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center shrink-0">
          <Camera className="w-5 h-5 text-amber-600" />
        </div>
        <div className="flex-1 min-w-0">
          <h4 className="font-bold text-gray-900 text-xs sm:text-sm truncate">
            {t("sellAnything")}
          </h4>
          <p className="text-[11px] sm:text-xs text-gray-500 truncate">
            {t("similarListingPrompt")}
          </p>
          <a
            href="/classified/new"
            className="text-xs font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 mt-0.5 hover:underline"
          >
            <span>{t("addNewListing")}</span>
            <ArrowRight className="w-3 h-3 rtl:rotate-180" />
          </a>
        </div>
      </div>

      {/* 5. Promo Card 2: Want more views? */}
      <div className="w-full bg-white border border-gray-200 rounded-xl p-4 shadow-xs flex items-center gap-3.5">
        <div className="w-11 h-11 rounded-xl bg-purple-50 border border-purple-200 flex items-center justify-center shrink-0">
          <Flame className="w-5 h-5 text-purple-600" />
        </div>
        <div className="flex-1 min-w-0">
          <h4 className="font-bold text-gray-900 text-xs sm:text-sm truncate">
            {t("wantMoreViews")}
          </h4>
          <p className="text-[11px] sm:text-xs text-gray-500 truncate">
            {t("promoteOrRepost")}
          </p>
          <a
            href="/classified/promote"
            className="text-xs font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 mt-0.5 hover:underline"
          >
            <span>{t("exploreMore")}</span>
            <ArrowRight className="w-3 h-3 rtl:rotate-180" />
          </a>
        </div>
      </div>
    </aside>
  );
}
