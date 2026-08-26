import { ICustomerProfile } from "@/types";
import { useEffect, useState, useCallback } from "react";
import {
  useMutation,
  useQuery,
  useQueryClient,
  UseQueryResult,
} from "@tanstack/react-query";
import { setCookie, deleteCookie, getCookie } from "cookies-next";
import { useQueryState } from "nuqs";
import toast from "react-hot-toast";
import { useTranslations } from "next-intl";
import { loginFormValues } from "../components/shared/auth/login/schema";
import { registerFormValues } from "../components/shared/auth/register/schema";
import { useCartContext } from "../providers/cart-provider";
import { loginService, registerService } from "../services/auth";
import {
  getProfile,
  updateProfile,
  UpdateProfilePayload,
} from "../services/profile";
import { ApiRequestError } from "../lib/utils";

const PROFILE_QUERY_KEY = ["profile"];
export const useAuth = () => {
  const queryClient = useQueryClient();

  const t = useTranslations("authDialog");
  const { resetGuestCart, mergeCart } = useCartContext();
  const [authDialogParam, setAuthDialogParam] = useQueryState("authDialog");
  const authDialogIsOpen = authDialogParam === "on";
  const setAuthDialogIsOpen = useCallback(
    (open: boolean) => {
      setAuthDialogParam(open ? "on" : null);
    },
    [setAuthDialogParam],
  );
  const [isLogged, setIsLogged] = useState<boolean>(() => {
    if (typeof window === "undefined") return false;
    const token = getCookie("access_token");
    const refreshToken = getCookie("refresh_token");
    return !!token || !!refreshToken;
  });

  const profileQuery: UseQueryResult<ICustomerProfile, ApiRequestError> =
    useQuery({
      queryKey: PROFILE_QUERY_KEY,
      queryFn: getProfile,
      enabled: isLogged,
      retry: (failureCount, err) => {
        // if unauthorized, force logout and stop retrying
        if ((err as ApiRequestError)?.status === 401) {
          logout();
          return false;
        }
        return true;
      },
    });

  // const invalidateProfile = useCallback(
  //   () => queryClient.invalidateQueries({ queryKey: PROFILE_QUERY_KEY }),
  //   [queryClient],
  // );

  const setProfileData = useCallback(
    (profile: ICustomerProfile | null) => {
      queryClient.setQueryData(PROFILE_QUERY_KEY, profile);
    },
    [queryClient],
  );

  // login mutation
  const login = useMutation({
    mutationKey: ["login"],
    mutationFn: (body: loginFormValues) => loginService(body),
    onSuccess: (res) => {
      const { data } = res;
      setToken({
        token: data.access_token,
        expiresIn: data.expires_in,
        refreshToken: data.refresh_token,
      });
      setProfileData(data.customer);
      mergeCart();
      toast.success(`${t("welcome")} ${data.customer.name}`);
    },
  });

  // register mutation
  const register = useMutation({
    mutationKey: ["register"],
    mutationFn: (body: registerFormValues) => registerService(body),
    onSuccess: (res) => {
      const { data } = res;
      setToken({
        token: data.access_token,
        expiresIn: data.expires_in,
        refreshToken: data.refresh_token,
      });
      setProfileData(data.customer);
      mergeCart();
      toast.success(`${t("welcome")} ${data.customer.name}`);
    },
  });

  // update customer info mutation
  const updateCustomer = useMutation({
    mutationKey: ["updateCustomerInfo"],
    mutationFn: async (body: UpdateProfilePayload) => updateProfile(body),
    onSuccess: (res) => {
      setProfileData(res);
      toast.success(`${t("profileUpdated")}`);
    },
  });

  const setToken = useCallback(
    ({
      token,
      expiresIn,
      refreshToken,
    }: {
      token: string;
      expiresIn: number;
      refreshToken: string;
    }) => {
      if (!token || !expiresIn || !refreshToken) return;
      setCookie("access_token", token, { maxAge: expiresIn });
      setCookie("refresh_token", refreshToken, { maxAge: 30 * 24 * 60 * 60 });
      setIsLogged(true);
      setAuthDialogParam(null);
      setAuthDialogIsOpen(false);
    },
    [setAuthDialogIsOpen, setAuthDialogParam],
  );

  const protectedWithAuth = useCallback(
    <T>(fn: () => T): T | undefined => {
      if (!isLogged) {
        setAuthDialogIsOpen(true);
        return undefined;
      }
      return fn();
    },
    [isLogged, setAuthDialogIsOpen],
  );

  const logout = () => {
    deleteCookie("access_token");
    deleteCookie("refresh_token");
    resetGuestCart();
    setProfileData(null);
    setIsLogged(false);
  };

  useEffect(() => {
    const token = getCookie("access_token");
    if (!token) {
      deleteCookie("access_token");
    }
  }, []);

  useEffect(() => {
    if (authDialogParam === "on" && isLogged) {
      setAuthDialogParam(null);
    } else if (authDialogParam === "on") {
      setAuthDialogIsOpen(true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [authDialogParam, isLogged]);

  return {
    profile: profileQuery.data || null,
    isLogged,
    isLogging: login.isPending,
    login: login.mutate,
    loginIsError: login.isError,
    loginError: login.error,
    isRegistering: register.isPending,
    register: register.mutate,
    registerIsError: register.isError,
    registerError: register.error,
    logout,
    authDialogIsOpen,
    setAuthDialogIsOpen,
    protectedWithAuth,
    updateCustomer: updateCustomer.mutate,
    updateCustomerError: updateCustomer.error,
    updateCustomerIsPending: updateCustomer.isPending,
    updateCustomerIsError: updateCustomer.isError,
  };
};
