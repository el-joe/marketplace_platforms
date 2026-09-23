"use client";

import { useCurrencies } from "@/src/hooks/use-currencies";
import {
  CurrencyCode,
  getCurrencySymbol,
} from "@/src/helpers/get-currency-symbol";
import Image from "next/image";

type Props = {
  code: CurrencyCode;
  className?: string;
  imageClassName?: string;
};

/**
 * Renders a currency's display symbol as either text or an image (e.g. an
 * official currency glyph as SVG), per the admin-configured symbol_type.
 * Falls back to the static text symbol map while the API data is loading
 * or unavailable, so price displays never block on the network.
 */
const CurrencySymbol = ({ code, className, imageClassName }: Props) => {
  const { data: currencies } = useCurrencies();
  const currency = currencies?.find((c) => c.code === code.toUpperCase());

  if (currency?.symbol_type === "image" && currency.symbol_image_url) {
    return (
      <Image
        src={currency.symbol_image_url}
        alt={currency.code}
        className={imageClassName ?? "inline-block h-3 w-auto align-middle"}
        width={32}
        height={32}
      />
    );
  }

  return (
    <span className={className}>
      {currency?.symbol ?? getCurrencySymbol(code)}
    </span>
  );
};

export default CurrencySymbol;
