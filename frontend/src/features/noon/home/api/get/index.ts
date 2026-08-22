import { fetchInstance } from "@/src/lib/utils";
import { IHome } from "../../types";

export const getHomeService = () => fetchInstance<{ data: IHome }>("/home");
