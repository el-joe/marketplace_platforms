import {
  BookmarkIcon,
  CreditCardIcon,
  KeyRoundIcon,
  MapPinIcon,
  MessageCircleIcon,
  RefreshCcwIcon,
  ShieldCheckIcon,
  ShieldOffIcon,
  TicketIcon,
  TruckIcon,
  UserIcon,
  WalletIcon,
  type LucideIcon,
} from "lucide-react";

/**
 * `help_center_categories.icon` stores an icon URL (or null), not a
 * component — the help-sheet UI renders a `lucide-react` component
 * (`HelpCard` does `<Icon className="..." />`), so a URL can't be dropped in
 * directly without changing that markup. This keeps the previous, known
 * category slugs visually identical to the old static mock tree and falls
 * back to a generic icon for any category slug the backend introduces
 * later, so a newly-added admin category never breaks rendering.
 */
const ICON_BY_SLUG: Record<string, LucideIcon> = {
  orders: BookmarkIcon,
  "returns-warranty-refunds": ShieldOffIcon,
  returns: RefreshCcwIcon,
  warranty: ShieldCheckIcon,
  refunds: CreditCardIcon,
  payments: CreditCardIcon,
  "payment-methods": CreditCardIcon,
  "noon-credits": WalletIcon,
  cashback: WalletIcon,
  "noon-one-mashreq": CreditCardIcon,
  "noon-one": TicketIcon,
  "mashreq-cards": CreditCardIcon,
  "mashreq-credit-card-faq": CreditCardIcon,
  "noon-savings-account-faq": WalletIcon,
  "my-profile": UserIcon,
  "profile-details": UserIcon,
  addresses: MapPinIcon,
  "account-security": KeyRoundIcon,
  "pre-order-other": TruckIcon,
};

const DEFAULT_ICON: LucideIcon = MessageCircleIcon;

export function resolveHelpIcon(slug: string): LucideIcon {
  return ICON_BY_SLUG[slug] ?? DEFAULT_ICON;
}
