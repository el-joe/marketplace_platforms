import axios from "axios";
import { resolveApiFilters } from "@/src/helpers/resolveApiFilters";
import resolveCookie from "@/src/helpers/resolveCookie";
import { region } from "@/src/utils/region";
const baseAPI = process.env.NEXT_PUBLIC_BASE_API_URL;

const axiosInstance = axios.create({
  baseURL: `${baseAPI}/${region}`,
});

// ─── Request Interceptor ────────────────────────────────────────────────────
axiosInstance.interceptors.request.use(async (config) => {
  const [token, filters] = await Promise.all([
    resolveCookie("access_token"),
    resolveApiFilters(),
  ]);

  config.headers = config.headers ?? {};
  config.headers.Authorization = `Bearer ${token}`;

  const endpointFilter = filters.find((filter) => {
    const urlSegments = config.url?.split("?")[0].split("/").filter(Boolean);

    return urlSegments?.includes(filter.targetEndpoint);
  });

  if (endpointFilter) {
    config.params = {
      ...(config.params ?? {}),
      ...endpointFilter.filters,
    };
  }

  return config;
});

// ─── Response Interceptor ────────────────────────────────────────────────────

axiosInstance.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.status === 401) {
    }
    return Promise.reject(error);
  },
);

export default axiosInstance;
