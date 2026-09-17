import { useMutation } from "@tanstack/react-query";
import { useState } from "react";
import toast from "react-hot-toast";
import { useTranslations } from "next-intl";
import { uploadBankTransferProofService } from "../../api/post";

export const useBankTransferProof = (orderNumber: string) => {
  const t = useTranslations("checkoutSuccess");
  const [file, setFile] = useState<File | null>(null);
  const [note, setNote] = useState("");

  const uploadProof = useMutation({
    mutationFn: () =>
      uploadBankTransferProofService(orderNumber, file as File, note),
    onSuccess: () => {
      toast.success(t("proofUploaded"));
      setFile(null);
    },
    onError: () => {
      toast.error(t("proofUploadFailed"));
    },
  });

  return {
    file,
    setFile,
    note,
    setNote,
    submitProof: () => file && uploadProof.mutate(),
    isUploading: uploadProof.isPending,
    isUploaded: uploadProof.isSuccess,
  };
};
