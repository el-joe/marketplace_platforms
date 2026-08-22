import { fetchInstance } from "@/src/lib/utils";
import { IPaymentGateway } from "../types";

export const getPaymentGateways = () =>
  fetchInstance<{ data: { gateways: IPaymentGateway[] } }>("/payment-gateways");
