"use client";
import { createNavigation } from "next-intl/navigation";
import { getRouting } from "./routing";

const routing = await getRouting();
export const { Link, redirect, usePathname, useRouter, getPathname } =
  createNavigation(routing);
