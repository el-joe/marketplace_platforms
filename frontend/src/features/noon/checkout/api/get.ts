import { fetchInstance } from "@/src/lib/utils";
import { IMarketerContractResponse } from "../types/marketer-contract.type";

export const getMarketerContract = (marketerId: string) =>
  fetchInstance<IMarketerContractResponse>(`/marketers/${marketerId}/contract`);
