import type { LucideIcon } from "lucide-react";

export type HelpActionType =
  | "NAVIGATE_ORDERS"
  | "NAVIGATE_RETURNS"
  | "NAVIGATE_WARRANTY"
  | "NAVIGATE_REFUNDS"
  | "NAVIGATE_PAYMENTS"
  | "NAVIGATE_NOON_CREDITS"
  | "NAVIGATE_CASHBACK"
  | "NAVIGATE_NOON_ONE"
  | "NAVIGATE_MASHREQ_CARDS"
  | "NAVIGATE_PROFILE"
  | "NAVIGATE_ADDRESSES"
  | "NAVIGATE_SECURITY"
  | "NAVIGATE_PREORDER"
  | "OPEN_CHAT";

export type HelpNodeType = "action" | "nested";

/**
 * How an "action" node renders its content inside the sheet.
 * - "items-list": status tabs + search/duration filter + an items list (or empty state)
 * - "faq": a list of question/description rows, each opening its own answer page
 * - "faq-detail": the full-answer page opened from a "faq" row
 */
export type HelpViewType = "items-list" | "faq" | "faq-detail";

export interface HelpFaqItem {
  id: string;
  question: string;
  description: string;
  answer: string;
}

export interface HelpListItem {
  id: string;
  title: string;
  subtitle: string;
  status: "in-progress" | "completed";
}

export interface HelpNode {
  id: string;
  title: string;
  description: string;
  icon: LucideIcon;
  type: HelpNodeType;
  children?: HelpNode[];
  actionType?: HelpActionType;
  viewType?: HelpViewType;
  faqItems?: HelpFaqItem[];
  listItems?: HelpListItem[];
  /** Set on a synthetic "faq-detail" node built from a HelpFaqItem — see faq-item-to-node.ts */
  faqAnswer?: string;
  faqParentTitle?: string;
}

export interface MenuState {
  stack: HelpNode[];
}
