import { format } from "date-fns";
import { useTranslations } from "next-intl";
import Card from "@/src/components/shared/Card";
import { Badge } from "@/src/components/ui/badge";
import { Separator } from "@/src/components/ui/separator";
import type { CaseDetails } from "../types";
import { priorityVariant, statusVariant } from "../case-status";

type Props = {
  data: CaseDetails;
};

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div>
      <p className="text-xs text-gray uppercase tracking-wide mb-1">{label}</p>
      <div className="text-sm font-semibold text-primary">{value}</div>
    </div>
  );
}

export default function CaseInfo({ data }: Props) {
  const t = useTranslations("support");

  return (
    <Card className="border border-border p-6 flex flex-col gap-4 lg:sticky lg:top-6">
      <div>
        <p className="text-xs text-gray uppercase tracking-wide mb-1">
          {data.mode === "ticket" ? t("columns.number") : t("columns.number")}
        </p>
        <h2 className="text-lg font-bold text-primary">
          {data.mode === "ticket" ? data.ticket_number : data.dispute_number}
        </h2>
      </div>

      <Badge variant={statusVariant(data.status)} className="w-fit">
        {t(`status.${data.status}`)}
      </Badge>

      <Separator />

      {data.mode === "ticket" ? (
        <>
          <InfoRow label={t("columns.subject")} value={data.subject} />
          <InfoRow
            label={t("columns.category")}
            value={t(`category.${data.category}`)}
          />
          <InfoRow
            label={t("columns.priority")}
            value={
              <Badge variant={priorityVariant(data.priority)}>
                {t(`priority.${data.priority}`)}
              </Badge>
            }
          />
        </>
      ) : (
        <>
          <InfoRow label={t("columns.order")} value={data.order_number} />
          <InfoRow
            label={t("columns.reason")}
            value={t(`reason.${data.reason}`)}
          />
          <InfoRow label={t("columns.description")} value={data.description} />
          {data.resolution && (
            <InfoRow
              label={t("resolution")}
              value={t(`resolutionType.${data.resolution}`)}
            />
          )}
          {data.compensation && (
            <InfoRow label={t("compensation")} value={data.compensation} />
          )}
          {data.resolution_notes && (
            <InfoRow
              label={t("resolutionNotes")}
              value={
                <span className="font-normal text-light">
                  {data.resolution_notes}
                </span>
              }
            />
          )}
        </>
      )}

      <Separator />

      <InfoRow
        label={t("createdAt")}
        value={format(new Date(data.created_at), "MMM d, yyyy 'at' h:mm a")}
      />
      {data.resolved_at && (
        <InfoRow
          label={t("resolvedAt")}
          value={format(new Date(data.resolved_at), "MMM d, yyyy 'at' h:mm a")}
        />
      )}
    </Card>
  );
}
