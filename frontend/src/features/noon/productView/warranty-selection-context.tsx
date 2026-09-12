"use client";
import { createContext, useContext, useState } from "react";
import { IProductDetails } from "./types";

interface IWarrantySelectionContext {
  selectedPlanId: string | null;
  selectPlan: (planId: string | null) => void;
  clearSelection: () => void;
  isSheetOpen: { state: boolean; cartItemId: null | string };
  setIsSheetOpen: (values: {
    state: boolean;
    cartItemId: null | string;
  }) => void;
  hadSelectedWarrantyOnAdd: boolean;
  handleProductAddedToCart: (
    hadWarrantySelected: boolean,
    cartItemId: string,
  ) => void;
  setHadSelectedWarrantyAfterAdd: (value: boolean) => void;
}

const warrantySelectionContext = createContext<IWarrantySelectionContext>({
  selectedPlanId: null,
  selectPlan: () => {},
  clearSelection: () => {},
  isSheetOpen: { state: false, cartItemId: null },
  setIsSheetOpen: () => {},
  hadSelectedWarrantyOnAdd: false,
  handleProductAddedToCart: () => {},
  setHadSelectedWarrantyAfterAdd: () => {},
});

export const WarrantySelectionProvider = ({
  children,
  productData,
}: {
  children: React.ReactNode;
  productData?: IProductDetails;
}) => {
  const [selectedPlanId, setSelectedPlanId] = useState<string | null>(null);
  const [isSheetOpen, setIsSheetOpen] = useState<{
    state: boolean;
    cartItemId: null | string;
  }>({ state: false, cartItemId: null });
  const [hadSelectedWarrantyOnAdd, setHadSelectedWarrantyOnAdd] =
    useState<boolean>(false);

  const handleProductAddedToCart = (
    hadWarrantySelected: boolean,
    cartItemId: string,
  ) => {
    setHadSelectedWarrantyOnAdd(hadWarrantySelected);
    const hasWarrantyPlans = (productData?.warranty_plans?.length ?? 0) > 0;
    const hasBoughtTogether =
      (productData?.frequently_bought_together?.items?.length ?? 0) > 1;

    const shouldShow =
      (hasWarrantyPlans && !hadWarrantySelected) || hasBoughtTogether;

    if (shouldShow) {
      setIsSheetOpen({ state: true, cartItemId });
    }
  };

  return (
    <warrantySelectionContext.Provider
      value={{
        selectedPlanId,
        selectPlan: setSelectedPlanId,
        clearSelection: () => setSelectedPlanId(null),
        isSheetOpen,
        setIsSheetOpen,
        hadSelectedWarrantyOnAdd,
        handleProductAddedToCart,
        setHadSelectedWarrantyAfterAdd: setHadSelectedWarrantyOnAdd,
      }}
    >
      {children}
    </warrantySelectionContext.Provider>
  );
};

export const useWarrantySelection = () => useContext(warrantySelectionContext);
