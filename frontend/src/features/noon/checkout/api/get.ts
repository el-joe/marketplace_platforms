import { fetchInstance } from "@/src/lib/utils";
import { IPaymentGateway } from "../types";
import { IMarketerContractResponse } from "../types/marketer-contract.type";

export const getPaymentGateways = () =>
  fetchInstance<{ data: { gateways: IPaymentGateway[] } }>("/payment-gateways");

export const getMarketerContract = (marketerId: string) =>
  fetchInstance<IMarketerContractResponse>(`/marketers/${marketerId}/contract`);
