import { fetchInstance } from "@/src/lib/utils";
import { IProductDetails } from "../../types";

export const getProduct = async (identifier: string): Promise<IProductDetails> => {
  const { data: res } = await fetchInstance<{ data: IProductDetails }>(
    `/l/${identifier}`,
  );
  return res;
};
