"use client";
import { SearchIcon } from "lucide-react";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "../ui/input-group";
import { useTranslations } from "next-intl";

const SearchField = () => {
  const t = useTranslations("header");
  return (
    <div className="flex-1 relative">
      <InputGroup className="h-11 text-base! bg-white!">
        <InputGroupInput placeholder={t("searchPlaceholder")} />
        <InputGroupAddon align="inline-start">
          <SearchIcon className="text-primary size-5" />
        </InputGroupAddon>
      </InputGroup>
    </div>
  );
};

export default SearchField;
