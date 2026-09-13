"use client";

import { JSXElementConstructor } from "react";
import { Sheet, SheetContent, SheetTrigger } from "@/src/components/ui/sheet";
import { useHelpTree } from "../api/use-help-tree";
import { useHelpNavigation } from "../helpers/use-help-navigation";
import LoadingCircles from "./loading-circles";
import NestedMenuView from "./nested-menu-view";
import ItemsListView from "./items-list-view";
import FaqView from "./faq-view";
import FaqDetailView from "./faq-detail-view";
import type { HelpNode } from "../types";

interface HelpSheetProps {
  triggerButton?: React.ReactElement<
    unknown,
    string | JSXElementConstructor<unknown>
  >;
  greeting?: string;
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
}

export default function HelpSheet({
  triggerButton,
  greeting,
  open,
  onOpenChange,
}: HelpSheetProps) {
  const { data: root, isLoading } = useHelpTree();
  const { current, canGoBack, goInto, goBack, reset } =
    useHelpNavigation(root);

  // Every card drills further into the sheet itself — nothing ever leaves it.
  const handleSelect = (node: HelpNode) => {
    goInto(node);
  };

  const handleOpenChange = (nextOpen: boolean) => {
    onOpenChange?.(nextOpen);
    if (!nextOpen) reset();
  };

  const handleClose = () => handleOpenChange(false);

  const renderContent = () => {
    if (!current) return null;

    if (current.type === "nested") {
      return (
        <NestedMenuView
          node={current}
          isRoot={!canGoBack}
          greeting={greeting}
          onSelect={handleSelect}
          onBack={goBack}
          onClose={handleClose}
        />
      );
    }

    if (current.viewType === "faq") {
      return (
        <FaqView
          node={current}
          onSelect={handleSelect}
          onBack={goBack}
          onClose={handleClose}
        />
      );
    }

    if (current.viewType === "faq-detail") {
      return (
        <FaqDetailView node={current} onBack={goBack} onClose={handleClose} />
      );
    }

    return (
      <ItemsListView node={current} onBack={goBack} onClose={handleClose} />
    );
  };

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      {triggerButton && <SheetTrigger render={triggerButton} />}
      <SheetContent
        showCloseButton={false}
        className="w-full max-w-[420px] overflow-hidden p-0"
      >
        <div
          className="flex h-full flex-col bg-cover bg-top bg-no-repeat bg-gray-5"
          style={{ backgroundImage: "url(/images/help-us-sheet-bg.avif)" }}
        >
          {isLoading || !current ? (
            <>
              <div className="flex justify-end p-6">
                <button
                  type="button"
                  onClick={handleClose}
                  aria-label="Close"
                  className="cursor-pointer text-xl leading-none"
                >
                  &times;
                </button>
              </div>
              <LoadingCircles />
            </>
          ) : (
            renderContent()
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
