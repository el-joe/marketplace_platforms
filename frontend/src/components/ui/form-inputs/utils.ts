import type { FieldErrors } from "react-hook-form";

export function getErrorMessage(
  errors: FieldErrors,
  name: string,
): string | undefined {
  const error = name
    .split(".")
    .reduce<unknown>(
      (acc, key) => (acc as Record<string, unknown> | undefined)?.[key],
      errors,
    );

  return (error as { message?: string } | undefined)?.message;
}
