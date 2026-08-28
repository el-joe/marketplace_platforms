import { fetchInstance } from "@/src/lib/utils";
import { IRecommendations } from "../types/recommendations.type";

export const getRecommendationsService = () =>
  fetchInstance<{ data: IRecommendations }>("/cart/recommendations");
