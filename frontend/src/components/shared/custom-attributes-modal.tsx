"use client";
import React, { useState } from "react";
import { Button } from "../ui/button";
import { useTranslations } from "next-intl";

export type CustomAttributeDefinition = {
  id: string;
  label: string;
  unit?: string | null;
  is_required: boolean;
  sort_order?: number;
  type?: "text" | "number" | "select" | "checkbox" | "notes";
  options?: string[];
  size_guide_image?: string | null;
};

type Props = {
  open: boolean;
  attributes: CustomAttributeDefinition[];
  onClose: () => void;
  onSubmit: (values: { product_custom_attribute_id: string; value: string }[]) => void;
  isSubmitting?: boolean;
};

/**
 * Shared modal used by both the PDP add-to-cart handler and the product-card
 * quick-add flow to collect customer-entered custom attribute values before
 * the add-to-cart API call fires. Callers should skip rendering this
 * component entirely when `product.has_custom_attributes` is false.
 */
export default function CustomAttributesModal({
  open,
  attributes,
  onClose,
  onSubmit,
  isSubmitting = false,
}: Props) {
  const t = useTranslations("productView");
  const [values, setValues] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);

  if (!open) return null;

  const sizeGuide = attributes.find((a) => a.size_guide_image)?.size_guide_image;
  const sorted = [...attributes].sort(
    (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0),
  );

  const handleSubmit = () => {
    const missing = sorted.find(
      (attr) =>
        attr.is_required &&
        (attr.type === "checkbox"
          ? values[attr.id] !== "1"
          : !values[attr.id]?.trim()),
    );
    if (missing) {
      setError(`${missing.label} is required`);
      return;
    }

    const payload = sorted
      .filter((attr) => values[attr.id]?.trim())
      .map((attr) => ({
        product_custom_attribute_id: attr.id,
        value: values[attr.id].trim(),
      }));

    onSubmit(payload);
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      onClick={onClose}
    >
      <div
        className="bg-white rounded-lg w-full max-w-md p-4 md:p-6 flex flex-col gap-4"
        onClick={(e) => e.stopPropagation()}
      >
        <h3 className="text-sm md:text-base font-semibold">
          {t.has("customize") ? t("customize") : "Customize your item"}
        </h3>

        {sizeGuide && (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={sizeGuide} alt="Size guide" className="max-h-48 object-contain rounded-md border" />
        )}
        <div className="flex flex-col gap-3">
          {sorted.map((attr) => (
            <div key={attr.id} className="flex flex-col gap-1">
              <label className="text-xs md:text-sm font-medium">
                {attr.label}
                {attr.is_required && <span className="text-red"> *</span>}
                {attr.unit ? (
                  <span className="text-gray text-[10px] md:text-xs">
                    {" "}
                    ({attr.unit})
                  </span>
                ) : null}
              </label>
              {attr.type === "select" ? (
                <select
                  className="border border-border-color rounded-md px-2 py-1.5 text-xs md:text-sm"
                  value={values[attr.id] ?? ""}
                  onChange={(e) => setValues((p) => ({ ...p, [attr.id]: e.target.value }))}
                >
                  <option value="" />
                  {(attr.options ?? []).map((o) => (
                    <option key={o} value={o}>{o}</option>
                  ))}
                </select>
              ) : attr.type === "checkbox" ? (
                <input
                  type="checkbox"
                  className="h-4 w-4"
                  checked={values[attr.id] === "1"}
                  onChange={(e) => setValues((p) => ({ ...p, [attr.id]: e.target.checked ? "1" : "0" }))}
                />
              ) : attr.type === "notes" ? (
                <textarea
                  rows={3}
                  maxLength={1000}
                  className="border border-border-color rounded-md px-2 py-1.5 text-xs md:text-sm"
                  value={values[attr.id] ?? ""}
                  onChange={(e) => setValues((p) => ({ ...p, [attr.id]: e.target.value }))}
                />
              ) : (
                <input
                  type={attr.type === "number" ? "number" : "text"}
                  className="border border-border-color rounded-md px-2 py-1.5 text-xs md:text-sm"
                  value={values[attr.id] ?? ""}
                  onChange={(e) => setValues((p) => ({ ...p, [attr.id]: e.target.value }))}
                />
              )}
            </div>
          ))}
        </div>

        {error && <p className="text-xs text-red">{error}</p>}

        <div className="flex gap-2 justify-end">
          <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={isSubmitting}>
            Add to cart
          </Button>
        </div>
      </div>
    </div>
  );
}
