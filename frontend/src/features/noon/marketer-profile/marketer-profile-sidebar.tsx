import React from "react";
import { MarketerProfileInfo, MarketerProfileMarketer } from "./helpers/types";

interface Props {
  marketer: MarketerProfileMarketer;
  profile: MarketerProfileInfo;
}

const SOCIAL_ICONS: Record<string, string> = {
  instagram: "📸",
  tiktok: "🎵",
  youtube: "▶️",
  twitter: "𝕏",
  snapchat: "👻",
  facebook: "👤",
};

export default function MarketerProfileSidebar({ marketer, profile }: Props) {
  return (
    <aside className="w-full lg:w-64 shrink-0 space-y-5">
      <div className="bg-gray-50 rounded-xl p-4 grid grid-cols-2 gap-3">
        <div className="text-center">
          <div className="text-2xl font-black text-gray-900">{marketer.total_campaigns}</div>
          <div className="text-xs text-gray-500 mt-0.5">حملة</div>
        </div>
        <div className="text-center">
          <div className="text-2xl font-black text-green-600">{marketer.total_conversions}</div>
          <div className="text-xs text-gray-500 mt-0.5">مبيعة</div>
        </div>
      </div>

      {(profile.bio_ar || profile.bio_en) && (
        <div className="space-y-1">
          <h3 className="text-sm font-bold text-gray-800">نبذة</h3>
          <p className="text-sm text-gray-600 leading-relaxed">
            {profile.bio_ar ?? profile.bio_en}
          </p>
        </div>
      )}

      {marketer.country && (
        <div>
          <span className="text-xs text-gray-400">الدولة: </span>
          <span className="text-xs font-semibold text-gray-700">{marketer.country.name_ar}</span>
        </div>
      )}

      {Object.keys(profile.social_links ?? {}).length > 0 && (
        <div className="space-y-2">
          <h3 className="text-sm font-bold text-gray-800">التواصل الاجتماعي</h3>
          <div className="flex flex-wrap gap-2">
            {Object.entries(profile.social_links).map(([platform, url]) =>
              url ? (
                <a
                  key={platform}
                  href={url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-full text-xs font-medium text-gray-700 transition"
                >
                  <span>{SOCIAL_ICONS[platform] ?? "🔗"}</span>
                  <span className="capitalize">{platform}</span>
                </a>
              ) : null,
            )}
          </div>
        </div>
      )}

      {profile.video_url && (
        <div>
          <h3 className="text-sm font-bold text-gray-800 mb-2">فيديو</h3>
          <div className="aspect-video rounded-xl overflow-hidden bg-gray-100">
            <iframe
              src={profile.video_url.replace("watch?v=", "embed/")}
              className="w-full h-full"
              allowFullScreen
              title="Marketer video"
            />
          </div>
        </div>
      )}
    </aside>
  );
}
