import { fetchInstance } from "@/src/lib/utils";
import { IClassified } from "../helpers/types";
import { ClassifiedItem } from "../../classifiedList/helpers/types";

export const getClassifiedDetailsService = (slug: string) =>
  fetchInstance<{ data: IClassified }>(`/listings/classified/${slug}`);

export const getRelatedClassifiedService = (slug: string) =>
  fetchInstance<{ data: { items: ClassifiedItem[] } }>(
    `/listings/classified/${slug}/similar`,
  );
