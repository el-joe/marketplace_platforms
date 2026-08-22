import { getProfile } from "@/src/services/profile";
import Profile from "@/src/features/noon/profile/profile";

export default async function ProfilePage() {
  const profile = await getProfile();

  return <Profile profile={profile} />;
}
