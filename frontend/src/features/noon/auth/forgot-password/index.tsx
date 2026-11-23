"use client";
import ForgotPasswordForm from "./components/forgot-password-form";

/** "Forgot password?" step of the storefront auth dialog. */
export default function ForgotPassword({ onBack }: { onBack: () => void }) {
  return <ForgotPasswordForm onBack={onBack} />;
}
