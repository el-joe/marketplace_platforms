"use client";

import React, { useState, useEffect, useRef } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Button } from "@/src/components/ui/button";
import { Skeleton } from "@/src/components/ui/skeleton";
import { Spinner } from "@/src/components/ui/spinner";
import {
  Receiver,
  getReceivers,
  createReceiver,
  setDefaultReceiver,
} from "@/src/services/receiver";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { ChevronDownIcon, LightbulbIcon, XCircleIcon } from "lucide-react";
import { cn } from "@/src/lib/utils";
import toast from "react-hot-toast";
import { getCookie } from "cookies-next";

export interface ReceiversDialogProps {
  triggerButton?: React.ReactElement;
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  selectedReceiverId?: string | null;
  onReceiverSelected?: (receiver: Receiver) => void;
}

const COUNTRY_OPTIONS = [
  { label: "UAE (+971)", code: "+971", key: "uae" },
  { label: "Egypt (+20)", code: "+20", key: "eg" },
  { label: "Saudi Arabia (+966)", code: "+966", key: "sa" },
  { label: "Kuwait (+965)", code: "+965", key: "kw" },
  { label: "Qatar (+974)", code: "+974", key: "qa" },
  { label: "Oman (+968)", code: "+968", key: "om" },
  { label: "Bahrain (+973)", code: "+973", key: "bh" },
];

function getInitials(name: string): string {
  if (!name) return "";
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "";
  if (parts.length === 1) return parts[0].slice(0, 2).toLowerCase();
  return (parts[0][0] + parts[1][0]).toLowerCase();
}

export default function ReceiversDialog({
  triggerButton,
  open: controlledOpen,
  onOpenChange,
  selectedReceiverId: initialSelectedId,
  onReceiverSelected,
}: ReceiversDialogProps) {
  const t = useTranslations("checkout");
  const queryClient = useQueryClient();

  const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
  const isControlled = controlledOpen !== undefined;
  const isOpen = isControlled ? controlledOpen : uncontrolledOpen;

  const setOpen = (open: boolean) => {
    if (!isControlled) {
      setUncontrolledOpen(open);
    }
    if (!open) {
      setName("");
      setPhone("");
      setIsCountryDropdownOpen(false);
      setIsSubmitting(false);
    }
    onOpenChange?.(open);
  };

  // Form states
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [selectedCountryCode, setSelectedCountryCode] = useState<string>(() => {
    const currentCountry = (getCookie("country") as string)?.toLowerCase();
    return (
      COUNTRY_OPTIONS.find((country) => country.key === currentCountry)?.code ??
      "+971"
    );
  });
  const [isCountryDropdownOpen, setIsCountryDropdownOpen] = useState(false);
  const [selectedSavedId, setSelectedSavedId] = useState<string | null>(null);
  const [saveForFutureOrders, setSaveForFutureOrders] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const countryDropdownRef = useRef<HTMLDivElement>(null);

  // Close country dropdown on outside click
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (
        countryDropdownRef.current &&
        !countryDropdownRef.current.contains(event.target as Node)
      ) {
        setIsCountryDropdownOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  // Fetch receivers list
  const { data: receivers = [], isLoading } = useQuery({
    queryKey: ["receivers"],
    queryFn: getReceivers,
    enabled: isOpen,
  });

  // Derive the initial selection during render instead of synchronously
  // updating state from an effect.
  const defaultReceiver = receivers.find((r) => r.is_default) || receivers[0];
  const effectiveSelectedId =
    selectedSavedId ??
    (!name && !phone
      ? (initialSelectedId ?? defaultReceiver?.id ?? null)
      : null);

  const handleNameChange = (val: string) => {
    setName(val);
    if (val.trim()) {
      setSelectedSavedId(null);
    }
  };

  const handlePhoneChange = (val: string) => {
    setPhone(val);
    if (val.trim()) {
      setSelectedSavedId(null);
    }
  };

  const handleSelectCountry = (code: string) => {
    setSelectedCountryCode(code);
    setIsCountryDropdownOpen(false);
    // If phone is empty or previously had a different country code, update it
    if (!phone || phone.startsWith("+")) {
      setPhone(code);
    }
    setSelectedSavedId(null);
  };

  const handleSelectSaved = (id: string) => {
    setSelectedSavedId(id);
    setName("");
    setPhone("");
  };

  // Mutations
  const createMutation = useMutation({
    mutationFn: createReceiver,
    onSuccess: (newReceiver) => {
      queryClient.invalidateQueries({ queryKey: ["receivers"] });
      onReceiverSelected?.(newReceiver);
      toast.success(t("receiverAddedSuccessfully"));
      setOpen(false);
    },
    onError: (err: unknown) => {
      const message =
        err instanceof Error ? err.message : "Failed to add receiver";
      toast.error(message);
    },
  });

  const setDefaultMutation = useMutation({
    mutationFn: setDefaultReceiver,
    onSuccess: (updatedReceiver) => {
      queryClient.invalidateQueries({ queryKey: ["receivers"] });
      onReceiverSelected?.(updatedReceiver);
      toast.success(t("receiverSetDefaultSuccessfully"));
      setOpen(false);
    },
    onError: (err: unknown) => {
      const message =
        err instanceof Error
          ? err.message
          : "Failed to update default receiver";
      toast.error(message);
    },
  });

  // Validation
  const isNewValid = name.trim().length > 0 && phone.trim().length > 0;
  const isSavedSelected = Boolean(effectiveSelectedId);
  const canSave = isNewValid || isSavedSelected;

  const handleSave = async () => {
    if (!canSave || isSubmitting) return;

    setIsSubmitting(true);
    try {
      if (isNewValid) {
        // Format phone: if user didn't type a plus or prefix and we have a selected prefix, prepend it
        let formattedPhone = phone.trim();
        if (
          !formattedPhone.startsWith("+") &&
          !formattedPhone.startsWith("00") &&
          selectedCountryCode
        ) {
          // If phone starts with leading 0 (e.g. 0501234567 or 01111111111), decide whether to preserve or clean
          // Let's pass the phone directly if it looks complete, or prefix
          if (formattedPhone.length < 10) {
            formattedPhone = `${selectedCountryCode}${formattedPhone}`;
          }
        }

        await createMutation.mutateAsync({
          name: name.trim(),
          phone: formattedPhone,
          is_default: saveForFutureOrders,
        });
      } else if (effectiveSelectedId) {
        const saved = receivers.find((r) => r.id === effectiveSelectedId);
        if (saved) {
          if (saveForFutureOrders && !saved.is_default) {
            await setDefaultMutation.mutateAsync(saved.id);
          } else {
            onReceiverSelected?.(saved);
            toast.success(t("receiverSelectedSuccessfully"));
            setOpen(false);
          }
        }
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={setOpen}>
      {triggerButton && <DialogTrigger render={triggerButton} />}
      <DialogContent className="max-w-[480px] p-6 rounded-2xl bg-white gap-4 text-black">
        <DialogHeader className="p-0">
          <DialogTitle className="text-base sm:text-lg font-bold text-gray-900 leading-tight">
            {t("receiversDialogTitle")}
          </DialogTitle>
        </DialogHeader>

        {/* Informational Banner */}
        <div className="flex items-start gap-3 p-3.5 rounded-xl bg-[#eef5fe] border border-[#d6e6fe]">
          <div className="shrink-0 mt-0.5 text-amber-500">
            <LightbulbIcon className="size-5 fill-amber-300 text-amber-500" />
          </div>
          <p className="text-xs sm:text-[13px] text-gray-700 leading-relaxed font-normal">
            {t("receiversBannerNotice")}
          </p>
        </div>

        {/* Section: Add receiver contact */}
        <div>
          <h4 className="text-sm font-semibold text-gray-900 mb-2.5">
            {t("addReceiverContact")}
          </h4>
          <div className="space-y-2.5">
            {/* Name input */}
            <div>
              <input
                type="text"
                placeholder={t("namePlaceholder")}
                value={name}
                onChange={(e) => handleNameChange(e.target.value)}
                className="w-full h-11 px-3.5 rounded-lg border border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-600 focus:outline-none transition-colors"
              />
            </div>

            {/* Phone Row */}
            <div className="flex items-center gap-2">
              {/* Country Select Dropdown */}
              <div className="relative shrink-0" ref={countryDropdownRef}>
                <button
                  type="button"
                  onClick={() => setIsCountryDropdownOpen((prev) => !prev)}
                  className="h-11 px-3 min-w-[96px] rounded-lg border border-gray-200 bg-white flex items-center justify-between gap-1.5 text-sm text-gray-800 hover:bg-gray-50 focus:border-blue-600 focus:outline-none transition-colors"
                >
                  <span className="truncate">
                    {selectedCountryCode || t("selectCountry")}
                  </span>
                  <ChevronDownIcon
                    className={cn(
                      "size-4 text-gray-500 transition-transform",
                      isCountryDropdownOpen && "rotate-180",
                    )}
                  />
                </button>

                {isCountryDropdownOpen && (
                  <div className="absolute left-0 top-full mt-1 w-48 max-h-52 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg z-50 py-1">
                    {COUNTRY_OPTIONS.map((item) => (
                      <button
                        key={item.code}
                        type="button"
                        onClick={() => handleSelectCountry(item.code)}
                        className={cn(
                          "w-full text-left px-3 py-2 text-xs hover:bg-blue-50 transition-colors flex items-center justify-between",
                          selectedCountryCode === item.code &&
                            "bg-blue-50 font-semibold text-blue-600",
                        )}
                      >
                        <span>{item.label}</span>
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {/* Phone Input with Clear Icon */}
              <div className="relative flex-1">
                <input
                  type="tel"
                  placeholder={t("phonePlaceholder")}
                  value={phone}
                  onChange={(e) => handlePhoneChange(e.target.value)}
                  className="w-full h-11 ps-3.5 pe-9 rounded-lg border border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-600 focus:outline-none transition-colors"
                />
                {phone.length > 0 && (
                  <button
                    type="button"
                    onClick={() => {
                      setPhone("");
                    }}
                    className="absolute inset-y-0 end-2.5 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                  >
                    <XCircleIcon className="size-4" />
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* OR Divider */}
        <div className="relative my-1 flex items-center justify-center">
          <div className="absolute inset-0 flex items-center">
            <div className="w-full border-t border-gray-200" />
          </div>
          <div className="relative bg-white px-3 text-xs font-semibold uppercase text-gray-400">
            {t("or")}
          </div>
        </div>

        {/* Section: Use previously saved contact */}
        <div>
          <h4 className="text-sm font-semibold text-gray-900 mb-2">
            {t("usePreviouslySavedContact")}
          </h4>

          {isLoading ? (
            <div className="space-y-2">
              <Skeleton className="h-12 w-full rounded-xl" />
              <Skeleton className="h-12 w-full rounded-xl" />
            </div>
          ) : receivers.length === 0 ? (
            <p className="text-xs text-gray-500 py-2 italic text-center">
              {t("noSavedReceivers")}
            </p>
          ) : (
            <div className="max-h-48 overflow-y-auto space-y-1.5 pe-1">
              {receivers.map((contact) => {
                const isSelected = effectiveSelectedId === contact.id;
                return (
                  <div
                    key={contact.id}
                    onClick={() => handleSelectSaved(contact.id)}
                    className={cn(
                      "flex items-center justify-between p-2.5 rounded-xl border transition-colors cursor-pointer select-none",
                      isSelected
                        ? "border-blue-500 bg-blue-50/25"
                        : "border-transparent hover:bg-gray-50",
                    )}
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      {/* Avatar with initials */}
                      <div className="size-9 rounded-full bg-[#fef3c7] text-[#92400e] flex items-center justify-center font-bold text-xs uppercase shrink-0">
                        {getInitials(contact.name)}
                      </div>
                      <div className="min-w-0">
                        <p className="text-sm font-semibold text-gray-900 truncate">
                          {contact.name}
                        </p>
                        <p className="text-xs text-gray-500">{contact.phone}</p>
                      </div>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">
                      {contact.is_default && (
                        <span className="text-xs font-semibold text-blue-600 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full">
                          {t("primaryBadge")}
                        </span>
                      )}
                      {/* Radio button */}
                      <div
                        className={cn(
                          "size-4 rounded-full border-2 flex items-center justify-center transition-colors bg-white",
                          isSelected ? "border-blue-600" : "border-gray-300",
                        )}
                      >
                        {isSelected && (
                          <div className="size-2 rounded-full bg-blue-600" />
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Checkbox: Save this for future orders */}
        <div className="pt-1">
          <label className="flex items-center gap-2.5 cursor-pointer select-none">
            <input
              type="checkbox"
              checked={saveForFutureOrders}
              onChange={(e) => setSaveForFutureOrders(e.target.checked)}
              className="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer accent-blue-600"
            />
            <span className="text-xs sm:text-sm text-gray-700 font-medium">
              {t("saveForFutureOrders")}
            </span>
          </label>
        </div>

        {/* SAVE Button */}
        <div className="pt-2">
          <Button
            type="button"
            disabled={!canSave || isSubmitting}
            onClick={handleSave}
            className={cn(
              "h-12 w-full rounded-xl font-bold uppercase tracking-wider text-sm transition-colors",
              canSave && !isSubmitting
                ? "bg-blue-600 hover:bg-blue-700 text-white shadow-sm"
                : "bg-[#f0f2f5] text-gray-400 cursor-not-allowed hover:bg-[#f0f2f5]",
            )}
          >
            {isSubmitting ? (
              <div className="flex items-center gap-2">
                <Spinner className="size-4" />
                <span>{t("saving")}</span>
              </div>
            ) : (
              t("save")
            )}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
