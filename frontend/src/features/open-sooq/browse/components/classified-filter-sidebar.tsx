"use client";

import { useCallback } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";

import { Button } from "@/src/components/ui/button";

const PURPOSE_OPTIONS = [
  { value: null, label: "All" },
  { value: "sale", label: "For Sale" },
  { value: "rent", label: "For Rent" },
] as const;

const SELLER_OPTIONS = [
  { value: null, label: "All Sellers" },
  { value: "vendor", label: "Stores Only" },
  { value: "customer", label: "Individuals" },
] as const;

const ClassifiedFilterSidebar = () => {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const updateFilter = useCallback(
    (key: string, value: string | null) => {
      const params = new URLSearchParams(searchParams.toString());
      if (value === null) {
        params.delete(key);
      } else {
        params.set(key, value);
        params.delete("page");
      }
      router.push(`${pathname}?${params.toString()}`);
    },
    [router, pathname, searchParams],
  );

  const clearAll = () => router.push(pathname);

  const purpose = searchParams.get("listing_purpose");
  const sellerType = searchParams.get("seller_type");
  const minPrice = searchParams.get("min_price");
  const maxPrice = searchParams.get("max_price");

  return (
    <div className="flex flex-col gap-6">
      {(purpose || sellerType || minPrice || maxPrice) && (
        <Button
          variant="ghost"
          className="text-xs text-blue-2 self-start p-0 h-auto"
          onClick={clearAll}
        >
          Clear all filters
        </Button>
      )}

      <div>
        <h4 className="text-sm font-bold mb-3 text-primary">Listing Type</h4>
        <div className="flex flex-col gap-2">
          {PURPOSE_OPTIONS.map((opt) => (
            <button
              key={opt.label}
              onClick={() => updateFilter("listing_purpose", opt.value)}
              className={`text-left text-sm py-1.5 px-3 rounded-lg transition-colors ${
                purpose === opt.value || (opt.value === null && !purpose)
                  ? "bg-blue text-white font-semibold"
                  : "text-gray hover:bg-gray-2"
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      <div>
        <h4 className="text-sm font-bold mb-3 text-primary">Seller</h4>
        <div className="flex flex-col gap-2">
          {SELLER_OPTIONS.map((opt) => (
            <button
              key={opt.label}
              onClick={() => updateFilter("seller_type", opt.value)}
              className={`text-left text-sm py-1.5 px-3 rounded-lg transition-colors ${
                sellerType === opt.value || (opt.value === null && !sellerType)
                  ? "bg-blue text-white font-semibold"
                  : "text-gray hover:bg-gray-2"
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      <div>
        <h4 className="text-sm font-bold mb-3 text-primary">Price Range</h4>
        <div className="flex gap-2">
          <input
            type="number"
            placeholder="Min"
            defaultValue={minPrice ?? ""}
            className="w-full border border-border rounded-lg px-3 py-2 text-sm"
            onBlur={(e) => updateFilter("min_price", e.target.value || null)}
          />
          <input
            type="number"
            placeholder="Max"
            defaultValue={maxPrice ?? ""}
            className="w-full border border-border rounded-lg px-3 py-2 text-sm"
            onBlur={(e) => updateFilter("max_price", e.target.value || null)}
          />
        </div>
      </div>
    </div>
  );
};

export default ClassifiedFilterSidebar;
