"use client";
import { Component, type ReactNode } from "react";
import { useTranslations } from "next-intl";
import { Trash2Icon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { useCartContext } from "@/src/providers/cart-provider";

type Props = { cartItemId: string; children: ReactNode };
type State = { hasError: boolean };

/** Keeps one malformed cart line from taking down the whole cart page; the shopper can still remove it. */
export default class CartItemBoundary extends Component<Props, State> {
  state: State = { hasError: false };

  static getDerivedStateFromError(): State {
    return { hasError: true };
  }

  componentDidCatch(error: unknown) {
    console.error("Cart item failed to render", this.props.cartItemId, error);
  }

  render() {
    if (this.state.hasError) {
      return <BrokenCartItem cartItemId={this.props.cartItemId} />;
    }
    return this.props.children;
  }
}

function BrokenCartItem({ cartItemId }: { cartItemId: string }) {
  const t = useTranslations("cart");
  const { removeItem, isMutating } = useCartContext();
  return (
    <div className="p-4 flex items-center gap-3 not-last:border-b border-dashed border-border">
      <p className="text-sm text-gray flex-1">{t("itemUnavailable")}</p>
      <Button
        variant={"ghost"}
        className="text-red"
        onClick={() => removeItem(cartItemId)}
        disabled={isMutating}
      >
        <Trash2Icon />
        {t("remove")}
      </Button>
    </div>
  );
}
