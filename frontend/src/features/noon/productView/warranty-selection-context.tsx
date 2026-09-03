"use client";
import { createContext, useContext, useState } from "react";

interface IWarrantySelectionContext {
  selectedPlanId: string | null;
  selectPlan: (planId: string | null) => void;
  clearSelection: () => void;
}

const warrantySelectionContext = createContext<IWarrantySelectionContext>(
  {} as IWarrantySelectionContext,
);

export const WarrantySelectionProvider = ({
  children,
}: {
  children: React.ReactNode;
}) => {
  const [selectedPlanId, setSelectedPlanId] = useState<string | null>(null);

  return (
    <warrantySelectionContext.Provider
      value={{
        selectedPlanId,
        selectPlan: setSelectedPlanId,
        clearSelection: () => setSelectedPlanId(null),
      }}
    >
      {children}
    </warrantySelectionContext.Provider>
  );
};

export const useWarrantySelection = () => useContext(warrantySelectionContext);
