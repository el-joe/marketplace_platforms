import { fetchInstance } from "@/src/lib/utils";
import { IClassifiedCategoriesList, IClassifiedsList } from "../helpers/types";

export const getClassifiedsService = ({
  category = "all",
}: {
  category?: string;
}) =>
  fetchInstance<{ data: IClassifiedsList }>(`/browse/classified/${category}`, {
    method: "GET",
  });

export const getClassifiedCategoriesService = () =>
  fetchInstance<{ data: IClassifiedCategoriesList[] }>(`/categories`, {
    method: "GET",
  });
