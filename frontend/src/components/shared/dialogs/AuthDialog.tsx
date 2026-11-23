"use client";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  // DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { useTranslations } from "next-intl";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/src/components/ui/tabs";
import Image from "next/image";
import { useAuthContext } from "@/src/providers/auth-provider";
import { useState } from "react";
import LoginForm from "../auth/login/login-form";
import RegisterForm from "../auth/register/register-form";
import ForgotPassword from "@/src/features/noon/auth/forgot-password";
const AuthDialog = () => {
  // const AuthDialog = ({ triggerButton }: props) => {
  const { authDialogIsOpen, setAuthDialogIsOpen } = useAuthContext();
  const t = useTranslations("authDialog");
  const [view, setView] = useState<"auth" | "forgot">("auth");
  return (
    <Dialog
      open={authDialogIsOpen}
      onOpenChange={() => {
        setAuthDialogIsOpen(false);
        setView("auth");
      }}
    >
      {/* <DialogTrigger render={triggerButton} /> */}
      <DialogContent className={"max-w-xl max-h-screen overflow-auto"}>
        <DialogHeader>
          <div
            className="relative h-80 max-w-xl -mx-4 -mt-4 overflow-hidden"
            dir="ltr"
          >
            <div className="absolute w-275 h-full  animate-inf-y-scroll">
              <Image
                src={"/images/auth-dialog-header-bg.png"}
                alt="background"
                fill
                sizes="100%"
                className="object-cover"
              />
            </div>
            <div className="absolute w-275 h-full  animate-inf-y-scroll left-[calc(100%+(1100px-100%))]">
              <Image
                src={"/images/auth-dialog-header-bg.png"}
                alt="background"
                fill
                sizes="100%"
                className="object-cover"
              />
            </div>
          </div>
        </DialogHeader>
        {view === "forgot" ? (
          <ForgotPassword onBack={() => setView("auth")} />
        ) : (
          <>
            <h2 className="text-center text-xl font-bold">{t("title")}</h2>
            <Tabs defaultValue={"login"}>
              <TabsList className={"mx-auto"}>
                <TabsTrigger value={"login"} className={"flex-1"}>
                  {t("login")}
                </TabsTrigger>
                <TabsTrigger value={"signup"} className={"flex-1"}>
                  {t("signup")}
                </TabsTrigger>
              </TabsList>
              {/* login tab */}
              <TabsContent value={"login"}>
                <LoginForm onForgotPassword={() => setView("forgot")} />
              </TabsContent>
              {/* sign up tab */}
              <TabsContent value={"signup"}>
                <RegisterForm />
              </TabsContent>
            </Tabs>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
};

export default AuthDialog;
