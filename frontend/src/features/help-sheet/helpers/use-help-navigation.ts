"use client";

import { useCallback, useMemo, useState } from "react";
import type { HelpNode } from "../types";

export function useHelpNavigation(root: HelpNode | undefined) {
  const [stack, setStack] = useState<HelpNode[]>([]);

  const current = useMemo<HelpNode | undefined>(() => {
    if (stack.length > 0) return stack[stack.length - 1];
    return root;
  }, [stack, root]);

  const canGoBack = stack.length > 0;

  const goInto = useCallback((node: HelpNode) => {
    setStack((prev) => [...prev, node]);
  }, []);

  const goBack = useCallback(() => {
    setStack((prev) => prev.slice(0, -1));
  }, []);

  const reset = useCallback(() => {
    setStack([]);
  }, []);

  return { current, canGoBack, goInto, goBack, reset };
}
