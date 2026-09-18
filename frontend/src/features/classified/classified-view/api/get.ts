import { fetchInstance } from "@/src/lib/utils";
import { IClassified } from "../helpers/types";
import { ClassifiedItem } from "../../classifiedList/helpers/types";

export const getClassifiedDetailsService = (slug: string) =>
  fetchInstance<{ data: IClassified }>(`/listings/classified/${slug}`);

export const getRelatedClassifiedService = (slug: string) =>
  fetchInstance<{ data: { items: ClassifiedItem[] } }>(
    `/listings/classified/${slug}/similar`,
  );

export const postClassifiedInquiryService = (slug: string, message: string) =>
  fetchInstance<{ data: unknown; message: string }>(
    `/listings/classified/${slug}/inquiries`,
    {
      method: "POST",
      body: JSON.stringify({ message }),
    },
  );
