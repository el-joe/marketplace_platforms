"use client";
import { ICustomerProfile } from "@/types";
import { createContext, useContext } from "react";
import { loginFormValues } from "../components/shared/auth/login/schema";
import { registerFormValues } from "../components/shared/auth/register/schema";
import { ProfileFormValues } from "../features/noon/profile/profile/helpers/profile.schema";
import { useAuth } from "../hooks/use-auth";
import { UpdateProfilePayload } from "../services/profile";

interface IAuthContext {
  profile: ICustomerProfile | null;
  isLogged: boolean;
  isLogging: boolean;
  loginIsError: boolean;
  loginError: Error | null;
  login: (credential: loginFormValues) => void;
  logout: () => void;
  authDialogIsOpen: boolean;
  setAuthDialogIsOpen: (open: boolean) => void;
  isRegistering: boolean;
  register: (credential: registerFormValues) => void;
  registerError: Error | null;
  registerIsError: boolean;
  protectedWithAuth: <T>(fn: () => T) => T | undefined;
  updateCustomer: (data: UpdateProfilePayload) => void;
  updateCustomerError: Error | null;
  updateCustomerIsPending: boolean;
  updateCustomerIsError: boolean;
}

const initialState: IAuthContext = {
  profile: null,
  isLogged: false,
  isLogging: false,
  loginIsError: false,
  loginError: null,
  login: () => {},
  logout: () => {},
  authDialogIsOpen: false,
  setAuthDialogIsOpen: () => {},
  isRegistering: false,
  register: () => {},
  registerError: null,
  registerIsError: false,
  protectedWithAuth() {},
  updateCustomer: () => {},
  updateCustomerError: null,
  updateCustomerIsPending: false,
  updateCustomerIsError: false,
};

const authContext = createContext<IAuthContext>(initialState);

export const AuthProvider = ({ children }: { children: React.ReactNode }) => {
  const {
    profile,
    isLogged,
    isLogging,
    login,
    loginError,
    loginIsError,
    logout,
    authDialogIsOpen,
    setAuthDialogIsOpen,
    isRegistering,
    register,
    registerError,
    registerIsError,
    protectedWithAuth,
    updateCustomer,
    updateCustomerError,
    updateCustomerIsPending,
    updateCustomerIsError,
  } = useAuth();
  return (
    <authContext.Provider
      value={{
        profile,
        isLogged,
        isLogging,
        login,
        loginError,
        loginIsError,
        logout,
        authDialogIsOpen,
        setAuthDialogIsOpen,
        isRegistering,
        register,
        registerError,
        registerIsError,
        protectedWithAuth,
        updateCustomer,
        updateCustomerError,
        updateCustomerIsPending,
        updateCustomerIsError,
      }}
    >
      {children}
    </authContext.Provider>
  );
};

export const useAuthContext = () => useContext(authContext);
