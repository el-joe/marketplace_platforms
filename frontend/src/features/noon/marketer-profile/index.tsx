import React from "react";
import ProductCard from "@/src/components/shared/product-card";
import MarketerProfileSidebar from "./marketer-profile-sidebar";
import MarketerProfileBanner from "./marketer-profile-banner";
import { toProductCard } from "./helpers/to-product-card";
import { MarketerProfileData } from "./helpers/types";

interface Props {
  data: MarketerProfileData;
}

export default function MarketerProfileView({ data }: Props) {
  const { marketer, profile, listings } = data;

  return (
    <div className="bg-white min-h-screen">
      <MarketerProfileBanner
        bannerUrl={profile.banner_url}
        marketerName={marketer.name}
        marketerType={marketer.marketer_type}
        profileUrl={profile.profile_url}
        qrCodeUrl={profile.qr_code_url}
      />

      <div className="container mx-auto px-4 py-8">
        <div className="flex flex-col lg:flex-row gap-8 items-start">
          <MarketerProfileSidebar marketer={marketer} profile={profile} />

          <div className="hidden lg:block w-px bg-gray-200 self-stretch" />

          <main className="flex-1">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-bold text-gray-900">
                المنتجات المختارة
                {listings.total > 0 && (
                  <span className="ms-2 text-sm font-normal text-gray-400">
                    ({listings.total} منتج)
                  </span>
                )}
              </h2>
            </div>

            {listings.items.length === 0 ? (
              <div className="text-center py-20">
                <div className="text-5xl mb-3">📦</div>
                <p className="text-gray-500">لا توجد منتجات مضافة بعد</p>
              </div>
            ) : (
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3 lg:gap-4">
                {listings.items.map((item) => (
                  <div key={item.listing_id} className="[&>div]:w-full">
                    <ProductCard productData={toProductCard(item, marketer, profile)} />
                  </div>
                ))}
              </div>
            )}
          </main>
        </div>
      </div>
    </div>
  );
}
