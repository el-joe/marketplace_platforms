"use client";

import React, { useEffect, useRef, useState } from "react";
import {
  Heart,
  Share2,
  Bell,
  Star,
  ChevronRight,
  Tag,
  Gauge,
  Fuel,
  Building2,
  Check,
} from "lucide-react";
import toast from "react-hot-toast";
import {
  addWishlistItemService,
  checkWishlistItemService,
  removeWishlistItemService,
} from "@/src/services/wishlist";
import { ClassifiedDetail } from "./types";

interface ClassifiedHeaderDetailsProps {
  listing: ClassifiedDetail;
}

export default function ClassifiedHeaderDetails({
  listing,
}: ClassifiedHeaderDetailsProps) {
  const [isFavorite, setIsFavorite] = useState(listing.isFavorite);
  const [favCount, setFavCount] = useState(listing.favoritesCount);
  const [isNotified, setIsNotified] = useState(false);
  const [copied, setCopied] = useState(false);
  const [isTogglingFavorite, setIsTogglingFavorite] = useState(false);
  const wishlistItemId = useRef<string | null>(null);

  // Sync the real favorite state from the wishlist on mount (the server-rendered
  // listing doesn't currently know the viewer's wishlist state).
  useEffect(() => {
    let cancelled = false;
    if (!listing.uuid) return;

    checkWishlistItemService(listing.uuid, "classified")
      .then((res) => {
        if (cancelled) return;
        const group = res.data.groups?.[0];
        wishlistItemId.current = group?.item_id ?? null;
        setIsFavorite(res.data.in_wishlist);
      })
      .catch(() => {
        // Non-fatal: leave the initial (default) favorite state as-is.
      });

    return () => {
      cancelled = true;
    };
  }, [listing.uuid]);

  const toggleFavorite = async () => {
    if (isTogglingFavorite || !listing.uuid) return;

    const wasFavorite = isFavorite;
    const previousItemId = wishlistItemId.current;

    // Optimistic update
    setIsFavorite(!wasFavorite);
    setFavCount((c) => (wasFavorite ? Math.max(0, c - 1) : c + 1));
    setIsTogglingFavorite(true);

    try {
      if (wasFavorite) {
        if (previousItemId) {
          await removeWishlistItemService(previousItemId);
        }
        wishlistItemId.current = null;
      } else {
        const res = await addWishlistItemService({
          listing_id: listing.uuid,
          item_type: "classified",
        });
        wishlistItemId.current = res.data.item.id;
      }
    } catch {
      // Roll back optimistic update on failure
      setIsFavorite(wasFavorite);
      setFavCount((c) => (wasFavorite ? c + 1 : Math.max(0, c - 1)));
      wishlistItemId.current = previousItemId;
      toast.error("Couldn't update favorites. Please try again.");
    } finally {
      setIsTogglingFavorite(false);
    }
  };

  const handleShare = async () => {
    if (navigator.share) {
      try {
        await navigator.share({
          title: listing.titleAr,
          text: `${listing.titleAr} - ${listing.price.toLocaleString()} ${listing.currency}`,
          url: window.location.href,
        });
      } catch {
        // User cancelled share
      }
    } else {
      navigator.clipboard.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  const getSpecIcon = (type: string) => {
    switch (type) {
      case "tag":
        return <Tag className="w-3.5 h-3.5 text-gray-500" />;
      case "speedometer":
        return <Gauge className="w-3.5 h-3.5 text-gray-500" />;
      case "gas":
        return <Fuel className="w-3.5 h-3.5 text-gray-500" />;
      case "dealership":
        return <Building2 className="w-3.5 h-3.5 text-gray-500" />;
      default:
        return null;
    }
  };

  return (
    <div className="w-full bg-white pt-4 pb-2 border-b border-gray-100">
      {listing.exclusiveContract && (
        <div className="mb-2.5 inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
          <Star className="w-3.5 h-3.5 fill-amber-500 text-amber-500" />
          <span>
            Exclusive contract{listing.exclusiveContract.marketerName
              ? ` — ${listing.exclusiveContract.marketerName}`
              : ""}
          </span>
          {listing.exclusiveContract.expiresAt && (
            <span className="text-amber-500">
              · until{" "}
              {new Date(
                listing.exclusiveContract.expiresAt,
              ).toLocaleDateString()}
            </span>
          )}
        </div>
      )}
      {/* Top Row: Price, Price Drop Link & Actions */}
      <div className="flex flex-wrap items-center justify-between gap-3 mb-2.5">
        {/* Left: Price and notification link */}
        <div className="flex items-baseline flex-wrap gap-2.5 sm:gap-3">
          <div className="text-2xl sm:text-3xl font-extrabold text-red-600 tracking-tight">
            {listing.price.toLocaleString()} {listing.currency}
          </div>
          <button
            onClick={() => setIsNotified(!isNotified)}
            className="text-xs sm:text-sm text-blue-600 hover:text-blue-700 hover:underline flex items-center gap-1 font-medium transition-colors"
          >
            <Bell
              className={`w-3.5 h-3.5 ${isNotified ? "fill-blue-600" : ""}`}
            />
            <span>
              {isNotified
                ? "Alert set for price drops"
                : "Notify me if price drops"}
            </span>
          </button>
        </div>

        {/* Right: Favourite & Share */}
        <div className="flex items-center gap-3">
          <button
            onClick={toggleFavorite}
            className="flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-gray-700 hover:text-red-500 transition-colors py-1 px-2 rounded-lg hover:bg-gray-50"
          >
            <Heart
              className={`w-4 h-4 transition-colors ${
                isFavorite ? "fill-red-500 text-red-500" : "text-gray-600"
              }`}
            />
            <span>Favourite ({favCount})</span>
          </button>

          <button
            onClick={handleShare}
            className="flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-gray-700 hover:text-blue-600 transition-colors py-1 px-2 rounded-lg hover:bg-gray-50"
          >
            {copied ? (
              <Check className="w-4 h-4 text-emerald-600" />
            ) : (
              <Share2 className="w-4 h-4 text-gray-600" />
            )}
            <span>{copied ? "Link Copied!" : "Share"}</span>
          </button>
        </div>
      </div>

      {/* Main Title (Arabic) */}
      <h1 className="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 leading-snug mb-1">
        {listing.titleAr}
      </h1>

      {/* English / Model Subtitle */}
      <p className="text-sm font-medium text-gray-600 mb-2">
        {listing.titleEn}
      </p>

      {/* Rating badge & Reviews link */}
      <div className="flex items-center gap-2 mb-3">
        <div className="inline-flex items-center gap-1 bg-emerald-700 text-white text-xs font-bold px-1.5 py-0.5 rounded">
          <Star className="w-3 h-3 fill-white text-white" />
          <span>{listing.rating.toFixed(1)}</span>
        </div>
        <button className="text-xs sm:text-sm text-gray-700 hover:text-blue-600 font-medium flex items-center hover:underline">
          <span>{listing.reviewsCount} Reviews</span>
          <ChevronRight className="w-3.5 h-3.5 rtl:rotate-180" />
        </button>
      </div>

      {/* Quick Specs Badges */}
      <div className="flex flex-wrap items-center gap-3 sm:gap-4 text-xs sm:text-sm text-gray-700">
        {listing.quickSpecs.map((spec, i) => (
          <div
            key={i}
            className="flex items-center gap-1.5 bg-gray-50 px-2 py-1 rounded border border-gray-100"
          >
            {getSpecIcon(spec.type)}
            <span className="font-medium text-gray-800">{spec.label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
