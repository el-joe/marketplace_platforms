import { useMutation, useQuery } from "@tanstack/react-query";
import {
  acceptMarketerContract,
  createPrepareCheckoutService,
  placeOrderService,
  uploadBankTransferProofService,
} from "../api/post";
import { useEffect, useMemo, useState } from "react";
import { getAddresses } from "@/src/services/address";
import { getPaymentGateways } from "@/src/services/payment-gateways";
import { v4 as uuidv4 } from "uuid";
import { useRouter } from "@/i18n/navigation";
import { IPrepareCheckout } from "../types/checkout.type";
import { getMarketerContract } from "../api/get";
import toast from "react-hot-toast";
import { useTranslations } from "next-intl";
import { ApiRequestError } from "@/src/lib/utils";
import { useAddressesContext } from "@/src/providers/addresses-provider";

export const useCheckout = () => {
  const router = useRouter();
  const t = useTranslations("checkout");

  const [checkoutData, setCheckoutData] = useState<
    IPrepareCheckout | undefined
  >(undefined);

  const [selectedInstruction, setSelectedInstruction] = useState<Record<
    string,
    string
  > | null>(null);

  const [selectedReceiverId, setSelectedReceiverId] = useState<string | null>(
    null,
  );

  const [isContractModalOpen, setIsContractModalOpen] = useState(false);
  const [contractAcceptances, setContractAcceptances] = useState<
    Record<string, string>
  >({});

  // const [selectedReceiverId, setSelectedReceiverId] = useState<string | null>(
  //   null,
  // );

  const [offlineProofFile, setOfflineProofFile] = useState<File | null>(null);
  const [offlineProofNote, setOfflineProofNote] = useState("");
  const [isUploadingProof, setIsUploadingProof] = useState(false);
  const [proofUploadFailed, setProofUploadFailed] = useState(false);

  const gateways = useQuery({
    queryKey: ["payment-gateways"],
    queryFn: getPaymentGateways,
  });
  const {
    selectedAddress,
    addresses,
    isLoading: isGettingAddresses,
  } = useAddressesContext();
  const selectedGateway = useMemo(
    () =>
      checkoutData?.available_payment_gateways.find(
        (g) => g.gateway_code === checkoutData?.gateway_code,
      ),
    [checkoutData?.available_payment_gateways, checkoutData?.gateway_code],
  );
  const selectedGatewayId = selectedGateway?.id;
  const isOfflinePaymentMethod = selectedGateway?.type === "offline";

  const contractGates = useMemo(() => {
    const gates =
      checkoutData?.marketer_contract_gates ??
      (checkoutData?.marketer_contract_gate
        ? [checkoutData.marketer_contract_gate]
        : []);
    return gates.filter((g) => g.is_required && !g.accepted);
  }, [checkoutData]);
  const pendingGates = contractGates.filter(
    (g) => !contractAcceptances[g.marketer_id],
  );
  const currentGate = pendingGates[0];
  const contractRequired = pendingGates.length > 0;

  const contractQuery = useQuery({
    queryKey: ["marketer-contract", currentGate?.marketer_id],
    queryFn: () => getMarketerContract(currentGate!.marketer_id),
    enabled: !!currentGate,
  });
  const contract = currentGate ? contractQuery.data?.contract : null;

  const acceptContract = useMutation({
    mutationFn: () =>
      acceptMarketerContract(currentGate!.marketer_id, contract!.version_id),
    onSuccess: (data) => {
      const remaining = pendingGates.length - 1;
      setContractAcceptances((prev) => ({
        ...prev,
        [currentGate!.marketer_id]: data.acceptance_id,
      }));
      if (remaining <= 0) setIsContractModalOpen(false);
    },
  });

  const prepareCheckout = useMutation({
    mutationFn: createPrepareCheckoutService,
    onMutate: () => toast.dismiss(),
    onSuccess: (data) => setCheckoutData(data.data),
    onError: (error) => {
      toast.error(error?.message);
    },
  });

  const placeOrder = useMutation({
    mutationFn: placeOrderService,
    onMutate: () => toast.dismiss(),
    onSuccess: async (data) => {
      const order = data?.data;
      const orderNumber = order?.order_number;
      if (typeof window !== "undefined" && order) {
        try {
          sessionStorage.setItem("last_placed_order", JSON.stringify(order));
        } catch (e) {
          console.error("Failed to save order to sessionStorage:", e);
        }
        try {
          const keysToRemove: string[] = [];
          for (let i = 0; i < sessionStorage.length; i++) {
            const key = sessionStorage.key(i);
            if (key?.startsWith("warranty-selection:")) keysToRemove.push(key);
          }
          keysToRemove.forEach((k) => sessionStorage.removeItem(k));
        } catch (e) {
          console.error("Failed to clear warranty selections:", e);
        }
      }

      if (isOfflinePaymentMethod && offlineProofFile && orderNumber) {
        setIsUploadingProof(true);
        try {
          await uploadBankTransferProofService(
            orderNumber,
            offlineProofFile,
            offlineProofNote,
          );
        } catch (e) {
          // Non-fatal: order is already placed. Let the customer retry from
          // the success page (bank-transfer-card.tsx fallback).
          console.error("Failed to upload payment proof:", e);
          setProofUploadFailed(true);
          toast.error(t("proofUploadFailedFallback"));
        } finally {
          setIsUploadingProof(false);
        }
      }

      if (order?.requires_redirect && order?.payment_redirect_url) {
        window.location.href = order.payment_redirect_url;
        return;
      }
      if (orderNumber) {
        router.push(`/checkout/success?order_number=${orderNumber}`);
      } else {
        router.push("/checkout/success");
      }
    },
    onError: (error) => {
      toast.error(error?.message);
    },
  });

  const prepare = (addressId: number, gatewayId?: string) => {
    prepareCheckout.mutate({
      address_id: addressId,
      country_payment_gateway_id: gatewayId as string,
      receiver_id: selectedReceiverId,
    });
  };

  const handleGatewayChange = async (gatewayId: string) => {
    if (!selectedAddress) return;
    prepare(Number(selectedAddress.id), gatewayId);
  };

  const acceptanceIds = contractGates
    .map((g) => contractAcceptances[g.marketer_id])
    .filter((id): id is string => !!id);

  const createOrder = () => {
    if (!selectedAddress || !selectedGatewayId) return;

    if (contractRequired) {
      setIsContractModalOpen(true);
      return;
    }

    if (isOfflinePaymentMethod && !offlineProofFile) {
      toast.error(t("fileRequiredError"));
      return;
    }

    setProofUploadFailed(false);

    const warrantySelections: {
      listing_id: string;
      warranty_plan_id: string;
    }[] = [];
    try {
      for (let i = 0; i < sessionStorage.length; i++) {
        const key = sessionStorage.key(i);
        if (key?.startsWith("warranty-selection:")) {
          const listingId = key.replace("warranty-selection:", "");
          const planId = sessionStorage.getItem(key);
          if (planId) {
            warrantySelections.push({
              listing_id: listingId,
              warranty_plan_id: planId,
            });
          }
        }
      }
    } catch {
      // sessionStorage unavailable — proceed without warranty selections
    }

    placeOrder.mutate({
      address_id: Number(selectedAddress.id),
      country_payment_gateway_id: selectedGatewayId,
      idempotency_key: uuidv4(),
      receiver_id: selectedReceiverId ?? undefined,
      delivery_instruction: !!selectedInstruction
        ? Object.entries(selectedInstruction)
            .map(([key, val]) => val)
            .join(", ")
        : undefined,
      coupon_code: checkoutData?.coupon?.code ?? null,
      wallet_amount_to_use: checkoutData?.wallet_applicable
        ? checkoutData.wallet_balance > 0
          ? checkoutData.wallet_balance
          : null
        : null,
      warranty_selections:
        warrantySelections.length > 0 ? warrantySelections : null,
      contract_acceptance_id: acceptanceIds[0] ?? null,
      contract_acceptance_ids: acceptanceIds.length > 0 ? acceptanceIds : null,
    });
  };

  const defaultGatewayId = gateways.data?.[0]?.id;

  useEffect(() => {
    if (!selectedAddress || !defaultGatewayId) return;
    prepare(Number(selectedAddress.id), defaultGatewayId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedAddress?.id, defaultGatewayId, selectedReceiverId]);

  return {
    createPrepareCheckout: prepareCheckout.mutateAsync,
    checkoutData,
    isPreparingCheckout: prepareCheckout.isPending,
    prepareCheckoutError: prepareCheckout.error,

    addressesData: addresses,
    isGettingAddresses,
    // addressesError: addresses.error,
    // selectedAddress,

    gatewaysData: gateways.data,
    isGettingGateways: gateways.isPending,
    gatewaysError: gateways.error,

    handleGatewayChange,
    selectedGatewayId,

    createOrder,
    isCreatingOrder: placeOrder.isPending || isUploadingProof,
    createOrderError: placeOrder.error,
    isPlacingOrder: placeOrder.isPending,
    isUploadingProof,
    proofUploadFailed,

    isOfflinePaymentMethod,
    offlineProofFile,
    setOfflineProofFile,
    offlineProofNote,
    setOfflineProofNote,

    selectedInstruction,
    setSelectedInstruction,

    selectedReceiverId,
    setSelectedReceiverId,

    contract,
    isContractModalOpen,
    closeContractModal: () => setIsContractModalOpen(false),
    acceptContract: acceptContract.mutate,
    isAcceptingContract: acceptContract.isPending,
  };
};
