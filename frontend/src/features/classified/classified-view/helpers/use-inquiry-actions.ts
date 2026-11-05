"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { ApiRequestError } from "@/src/lib/utils";
import useLocale from "@/src/hooks/use-locale";
import {
  signListingCustomerContract,
  type CustomerContractPayload,
} from "@/src/services/customer-contracts";
import { postClassifiedInquiryService } from "../api/get";
import {
  CONTRACT_CHANGED_CODE,
  CONTRACT_REQUIRED_CODE,
  SENT_BANNER_MS,
} from "./constants";
import { toContractPayload } from "./to-contract-payload";

/**
 * Sends the inquiry of a classified advertisement. When the backend answers that a customer purchase contract has to
 * be accepted first (`customer_contract_required`), the contract is handed to the modal; accepting it records the
 * acceptance server-side and re-sends the pending message. The decision is always the server's.
 */
export function useInquiryActions(slug: string) {
  const t = useTranslations("classifiedInquiry");
  const tc = useTranslations("customerContract");
  const locale = useLocale();

  const [message, setMessage] = useState("");
  const [isSending, setIsSending] = useState(false);
  const [isSent, setIsSent] = useState(false);
  const [pendingMessage, setPendingMessage] = useState("");
  const [contract, setContract] = useState<CustomerContractPayload | null>(null);
  const [isSigning, setIsSigning] = useState(false);
  const [signError, setSignError] = useState<string | null>(null);

  const deliver = async (text: string): Promise<boolean> => {
    setIsSending(true);
    try {
      await postClassifiedInquiryService(slug, text);
      setIsSent(true);
      setMessage("");
      setTimeout(() => setIsSent(false), SENT_BANNER_MS);
      return true;
    } catch (error) {
      if (
        error instanceof ApiRequestError &&
        error.code === CONTRACT_REQUIRED_CODE
      ) {
        const required = toContractPayload(error.data);
        if (required) {
          setPendingMessage(text);
          setSignError(null);
          setContract(required);
          return false;
        }
      }
      toast.error(t("sendFailed"));
      return false;
    } finally {
      setIsSending(false);
    }
  };

  const sendInquiry = async () => {
    const text = message.trim();
    if (!text || isSending) return;
    await deliver(text);
  };

  const acceptContract = async (signerName: string) => {
    if (!contract || isSigning) return;
    setIsSigning(true);
    setSignError(null);
    try {
      await signListingCustomerContract(slug, {
        signer_name: signerName,
        template_id: contract.template_id,
        version: contract.version,
        language: locale,
      });
      setContract(null);
      await deliver(pendingMessage);
    } catch (error) {
      if (
        error instanceof ApiRequestError &&
        error.code === CONTRACT_CHANGED_CODE
      ) {
        const updated = toContractPayload(error.data);
        if (updated) setContract(updated);
        setSignError(tc("changed"));
      } else {
        setSignError(
          error instanceof ApiRequestError && error.status === 401
            ? tc("loginRequired")
            : tc("acceptFailed"),
        );
      }
    } finally {
      setIsSigning(false);
    }
  };

  const closeContract = () => {
    if (isSigning) return;
    setContract(null);
    setSignError(null);
  };

  return {
    message,
    setMessage,
    isSending,
    isSent,
    sendInquiry,
    contract,
    isSigning,
    signError,
    acceptContract,
    closeContract,
  };
}
