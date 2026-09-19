"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { closeSpecialRequest } from "../api/special-requests.actions";

export function useRequestActions() {
  const t = useTranslations("specialRequests");
  const qc = useQueryClient();

  const close = useMutation({
    mutationFn: (id: string) => closeSpecialRequest(id),
    onSuccess: () => {
      toast.success(t("closed"));
      qc.invalidateQueries({ queryKey: ["special-requests"] });
    },
    onError: () => toast.error(t("closeFailed")),
  });

  return { closeRequest: close.mutate, isClosing: close.isPending };
}
