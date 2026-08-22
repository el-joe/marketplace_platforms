// import { region } from "./region";

// const BASE_API_URL = process.env.NEXT_PUBLIC_BASE_API_URL!;

// export async function fetchInstance<T>(
//   endpoint: string,
//   options: RequestInit = {},
// ): Promise<T> {
//   const response = await fetch(`${BASE_API_URL}/${region}/${endpoint}`, {
//     headers: {
//       "Content-Type": "application/json",
//       ...options.headers,
//     },
//     ...options,
//   });

//   if (!response.ok) {
//     const errorBody = await response.json();
//     if (!!errorBody) {
//       throw new Error(errorBody.message);
//     } else {
//       throw new Error("Request failed");
//     }
//   }

//   return response.json();
// }
