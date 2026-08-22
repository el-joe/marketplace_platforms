import ProfileLayout from "@/src/layout/noon/profile";
import LowerFooter from "@/src/layout/shared/lower-footer";

export default function ProfileLayoutPage({
  children,
}: {
  children: React.ReactNode;
}) {
  return <ProfileLayout>{children}</ProfileLayout>;
}
