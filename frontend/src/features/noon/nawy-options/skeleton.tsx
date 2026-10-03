import React from "react";

export default function NawyOptionsSkeleton() {
  return (
    <div className="container">
      <div className="flex h-dvh">
        <div className="w-1/4 h-full bg-accent p-3">
          {Array.from({ length: 10 }).map((e, i) => (
            <div
              key={i}
              className="w-full h-12 bg-white rounded mb-2 animate-pulse"
            />
          ))}
        </div>
      </div>
    </div>
  );
}
