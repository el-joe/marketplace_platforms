import ProfileSidebar from "./profile-sidebar";
import LowerFooter from "../../shared/lower-footer";

const ProfileLayout = ({ children }: { children: React.ReactNode }) => {
  return (
    <>
      <div className="container py-6 bg-gray-100">
        <div className="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-8 items-start">
          <ProfileSidebar />
          <div>{children}</div>
        </div>
      </div>
      <LowerFooter className="container py-4 px-10" />
    </>
  );
};

export default ProfileLayout;
