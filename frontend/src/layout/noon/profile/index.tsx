import ProfileSidebar from "./profile-sidebar";
import LowerFooter from "../../shared/lower-footer";
import { getFooterData } from "@/src/services/footer";
import getLocale from "@/src/helpers/getLocale";

const ProfileLayout = async ({ children }: { children: React.ReactNode }) => {
  const locale = await getLocale();
  const { bottom_nav_links, payment_methods } = await getFooterData();

  return (
    <>
      <div className="container py-6 bg-gray-100">
        <div className="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-8 items-start">
          <ProfileSidebar />
          <div>{children}</div>
        </div>
      </div>
      <LowerFooter
        className="container py-4 px-10"
        bottomNavLinks={bottom_nav_links}
        paymentMethods={payment_methods}
        locale={locale}
      />
    </>
  );
};

export default ProfileLayout;
