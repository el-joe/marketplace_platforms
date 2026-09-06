"use client";

import React, { useState } from "react";
import { Send, CheckCircle } from "lucide-react";

interface ClassifiedInquiryProps {
  sellerName?: string;
}

const PRESET_QUESTIONS = [
  "I'm interested",
  "Can you lower the price",
  "Where can we meet",
  "You do delivery",
  "What's the inspection result",
];

export default function ClassifiedInquiry({
  sellerName = "the lister",
}: ClassifiedInquiryProps) {
  const [message, setMessage] = useState("");
  const [isSent, setIsSent] = useState(false);

  const handleChipClick = (text: string) => {
    setMessage(text);
  };

  const handleSend = (e: React.FormEvent) => {
    e.preventDefault();
    if (!message.trim()) return;

    setIsSent(true);
    setTimeout(() => {
      setMessage("");
      setIsSent(false);
    }, 3500);
  };

  return (
    <div className="w-full mt-6 pt-5 border-t border-gray-100">
      <h2 className="text-base sm:text-lg font-bold text-gray-900 mb-3">
        Ask the Lister
      </h2>

      {/* Preset question pills */}
      <div className="flex flex-wrap gap-2 mb-3.5">
        {PRESET_QUESTIONS.map((q, idx) => (
          <button
            key={idx}
            type="button"
            onClick={() => handleChipClick(q)}
            className="text-xs sm:text-[13px] font-medium px-3 py-1.5 rounded-full bg-sky-50 hover:bg-sky-100 text-sky-900 border border-sky-200 transition-colors"
          >
            {q}
          </button>
        ))}
      </div>

      {/* Message input bar */}
      <form onSubmit={handleSend} className="flex items-center gap-2">
        <div className="relative flex-1">
          <input
            type="text"
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            placeholder="Message..."
            className="w-full text-xs sm:text-sm px-4 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder:text-gray-400"
          />
        </div>

        <button
          type="submit"
          disabled={!message.trim()}
          className={`px-4 sm:px-5 py-2.5 text-xs sm:text-sm font-semibold rounded-lg transition-colors shrink-0 flex items-center gap-1.5 ${
            message.trim()
              ? "bg-blue-600 text-white hover:bg-blue-700 cursor-pointer shadow-xs"
              : "bg-gray-400 text-white cursor-not-allowed"
          }`}
        >
          <span>Send Message</span>
          <Send className="w-3.5 h-3.5" />
        </button>
      </form>

      {/* Sent confirmation notification */}
      {isSent && (
        <div className="mt-2.5 flex items-center gap-1.5 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 p-2 rounded-lg animate-in fade-in">
          <CheckCircle className="w-4 h-4 text-emerald-600 shrink-0" />
          <span>Your message has been sent to {sellerName}!</span>
        </div>
      )}
    </div>
  );
}
