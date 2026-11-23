import { getTranslations } from "next-intl/server";
import ResetPassword from "@/src/features/noon/auth/reset-password";

type Props = {
  searchParams: Promise<{ [key: string]: string | string[] | undefined }>;
};

export async function generateMetadata() {
  const t = await getTranslations("resetPassword");

  return { title: t("title"), robots: { index: false, follow: false } };
}

const first = (value: string | string[] | undefined) =>
  (Array.isArray(value) ? value[0] : value) ?? "";

export default async function ResetPasswordPage({ searchParams }: Props) {
  const params = await searchParams;

  return (
    <ResetPassword token={first(params.token)} email={first(params.email)} />
  );
}
