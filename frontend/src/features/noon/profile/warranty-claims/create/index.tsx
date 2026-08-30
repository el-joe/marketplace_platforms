"use client";

import { useState } from "react";
import Image from "next/image";
import { useSearchParams, useRouter } from "next/navigation";
import { useMutation } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { AlertCircle, ShieldCheck } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { Button } from "@/src/components/ui/button";
import { Select } from "@/src/components/ui/base-inputs/select";
import { Textarea } from "@/src/components/ui/base-inputs/textarea";
import {
  submitWarrantyClaim,
  type IssueType,
} from "@/src/features/noon/profile/warranty/api/warranty.actions";

const ISSUE_TYPES: IssueType[] = [
  "defective",
  "not_working",
  "physical_damage",
  "missing_parts",
  "software_issue",
  "other",
];

export default function CreateWarrantyClaim() {
  const t = useTranslations("profile");
  const router = useRouter();
  const searchParams = useSearchParams();
  const orderItemId = searchParams.get("order_item_id");

  const [issueType, setIssueType] = useState<IssueType | null>(null);
  const [description, setDescription] = useState("");
  const [files, setFiles] = useState<File[]>([]);
  const [validationError, setValidationError] = useState<string | null>(null);

  const submit = useMutation({
    mutationFn: () => {
      if (!orderItemId || !issueType) {
        throw new Error(t("issueTypeRequired"));
      }
      return submitWarrantyClaim({
        order_item_id: orderItemId,
        issue_type: issueType,
        issue_description: description,
        evidence_files: files.length ? files : undefined,
      });
    },
    onSuccess: (claim) => {
      router.push(`/warranty-claims/${claim.claim_number}`);
    },
    onError: () => {
      setValidationError(t("claimSubmissionFailed"));
    },
  });

  if (!orderItemId) {
    return (
      <div className="flex min-h-[70vh] flex-col items-center justify-center text-center">
        <Image src="/images/profile/claims.svg" alt="" width={321} height={231} />

        <h2 className="mt-6 text-2xl font-bold">{t("noClaimableItems")}</h2>
        <p className="mt-1 text-sm text-gray">{t("noClaimableItemsMessage")}</p>

        <Button
          render={<Link href="/warranty-claims" />}
          nativeButton={false}
          className="mt-6 h-12 rounded-md bg-blue-3 px-8 font-semibold text-white uppercase"
        >
          {t("backToExistingClaims")}
        </Button>
      </div>
    );
  }

  const handleSubmit = () => {
    if (!issueType) {
      setValidationError(t("issueTypeRequired"));
      return;
    }
    if (description.trim().length < 10) {
      setValidationError(t("descriptionTooShort"));
      return;
    }
    setValidationError(null);
    submit.mutate();
  };

  return (
    <div className="mx-auto max-w-xl space-y-6 py-8">
      <div className="flex items-center gap-2">
        <ShieldCheck className="size-5 text-blue" />
        <h1 className="text-xl font-bold">{t("fileWarrantyClaim")}</h1>
      </div>

      <div className="space-y-2">
        <label className="text-sm font-medium">{t("issueType")}</label>
        <Select
          value={issueType}
          onValueChange={(value) => {
            setIssueType(value as IssueType);
            setValidationError(null);
          }}
          triggerClass="w-full h-11 justify-between"
          placeholder={t("selectIssueType")}
          items={ISSUE_TYPES.map((type) => ({
            label: t(`issueTypes.${type}`),
            value: type,
          }))}
        />
      </div>

      <div className="space-y-2">
        <label className="text-sm font-medium">{t("issueDescription")}</label>
        <Textarea
          placeholder={t("issueDescriptionPlaceholder")}
          value={description}
          onChange={(e) => {
            setDescription(e.target.value);
            setValidationError(null);
          }}
          minLength={10}
          maxLength={2000}
          className="min-h-[100px]"
        />
        <p className="text-xs text-gray">{description.length}/2000</p>
      </div>

      <div className="space-y-2">
        <label className="text-sm font-medium">
          {t("evidenceFiles")}{" "}
          <span className="font-normal text-gray">({t("optional")})</span>
        </label>
        <input
          type="file"
          accept="image/jpeg,image/png,application/pdf,video/mp4"
          multiple
          className="w-full text-sm"
          onChange={(e) => {
            const selected = Array.from(e.target.files ?? []).slice(0, 5);
            setFiles(selected);
          }}
        />
        <p className="text-xs text-gray">{t("evidenceFilesHint")}</p>
      </div>

      {validationError && (
        <div className="flex items-center gap-2 rounded-md border border-red bg-light-red px-3 py-2">
          <AlertCircle className="size-4 shrink-0 text-red" />
          <p className="text-sm text-red">{validationError}</p>
        </div>
      )}

      <div className="flex gap-3">
        <Button
          variant="outline"
          onClick={() => router.back()}
          className="flex-1"
          disabled={submit.isPending}
        >
          {t("cancel")}
        </Button>
        <Button
          onClick={handleSubmit}
          disabled={submit.isPending}
          className="flex-1 bg-blue-3 font-semibold text-white"
        >
          {submit.isPending ? t("submitting") : t("submitClaim")}
        </Button>
      </div>
    </div>
  );
}
