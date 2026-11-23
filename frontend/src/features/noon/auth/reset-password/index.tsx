import { getTranslations } from "next-intl/server";
import ResetPasswordForm from "./components/reset-password-form";

type Props = { token: string; email: string };

/** Storefront page opened from the "reset your password" email. */
export default async function ResetPassword({ token, email }: Props) {
  const t = await getTranslations("resetPassword");

  return (
    <section className="container py-10 md:py-16 flex justify-center">
      <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-sm ring-1 ring-foreground/10">
        <h1 className="text-xl font-bold mb-1">{t("title")}</h1>
        <p className="text-secondary mb-5">{t("description")}</p>
        <ResetPasswordForm token={token} email={email} />
      </div>
    </section>
  );
}
