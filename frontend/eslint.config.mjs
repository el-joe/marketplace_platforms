import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";
import reactPlugin from "eslint-plugin-react";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
  ]),
  // P-25: i18n guards — every page must read locale via the project wrapper
  // and every user-facing string must come from locale/en.json + ar.json.
  {
    files: ["src/**/*.{ts,tsx}", "app/**/*.{ts,tsx}"],
    // The wrapper itself and the two low-level hooks it wraps are the only
    // files allowed to import useLocale directly from next-intl.
    ignores: [
      "src/hooks/use-locale.ts",
      "src/hooks/use-handle-locale.ts",
      "src/hooks/use-region.ts",
    ],
    plugins: { react: reactPlugin },
    rules: {
      "no-restricted-imports": [
        "error",
        {
          paths: [
            {
              name: "next-intl",
              importNames: ["useLocale"],
              message:
                'Import useLocale from "@/src/hooks/use-locale" instead of "next-intl" directly (see frontend/CLAUDE.md and enhancement.md P-25).',
            },
          ],
        },
      ],
      "react/jsx-no-literals": [
        "warn",
        {
          noStrings: true,
          allowedStrings: [
            "OpenSooq",
            "•",
            "-",
            "/",
            ":",
            "|",
            "×",
            "(",
            ")",
            "%",
            "AED",
            "EGP",
            // Decorative emoji used as icons, not translatable content.
            "📹",
            "👁",
            "❤️",
            "🗓",
            "📼",
            "💬",
          ],
          ignoreProps: true,
          noAttributeStrings: false,
        },
      ],
    },
  },
]);

export default eslintConfig;
