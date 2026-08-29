import Home from "@/src/features/noon/home";
import HomeSkeleton from "@/src/features/noon/home/home-skeleton";
import { Suspense } from "react";

export default function page() {
  return (
    <HomeSkeleton />
    // <Suspense fallback={<HomeSkeleton />}>
    //   <Home />
    // </Suspense>
  );
}
