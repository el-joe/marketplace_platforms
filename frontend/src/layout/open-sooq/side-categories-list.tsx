import React from "react";
import { Button } from "@/src/components/ui/button";
import { MenuIcon } from "lucide-react";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/src/components/ui/sheet";
import Logo from "@/src/components/shared/Logo";

const SideCategoriesList = () => {
  return (
    <Sheet>
      <SheetTrigger
        render={
          <Button variant={"ghost"} className={"p-0"}>
            <MenuIcon className="size-5 md:hidden" />
          </Button>
        }
      />
      <SheetContent side="left">
        <SheetHeader>
          <SheetTitle>
            <Logo platform={"openSooq"} />
          </SheetTitle>
        </SheetHeader>
        {/*  */}
        {/*  */}
      </SheetContent>
    </Sheet>
  );
};

export default SideCategoriesList;
