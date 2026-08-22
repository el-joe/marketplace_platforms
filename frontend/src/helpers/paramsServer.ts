"use server"
import { headers } from "next/headers";

export const paramsServer = async () => {
    const headerParams = (await headers()).get("x-params") ?? "";
    return headerParams
}