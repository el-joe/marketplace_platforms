#!/usr/bin/env node
/**
 * P-25: fail CI when locale/en.json and locale/ar.json drift apart.
 *
 * Checks:
 *   1. Both files flatten to exactly the same set of dotted keys.
 *   2. No `ar` value is byte-identical to its `en` counterpart, unless the
 *      value is in ALLOWLIST (brand names, acronyms, currency codes, and
 *      other strings that are legitimately the same in both languages).
 *
 * Usage: node scripts/check-locale-parity.mjs
 * Exits 1 (and prints a report) on any violation, 0 otherwise.
 */
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const EN_PATH = path.join(__dirname, "..", "locale", "en.json");
const AR_PATH = path.join(__dirname, "..", "locale", "ar.json");

// Values that are intentionally identical in both locales: brand names,
// acronyms, currency/measurement codes, and punctuation-only strings.
const ALLOWLIST = new Set([
  "OpenSooq",
  "Enter",
  "AED",
  "EGP",
  "SAR",
  "USD",
  "KWD",
  "QAR",
  "BHD",
  "OMR",
  "SMS",
  "OTP",
  "QR",
  "PDF",
  "CVV",
  "VAT",
  "COD",
  "URL",
  "API",
  "ID",
  "IBAN",
  "-",
  "/",
  ":",
  "|",
  "•",
  "×",
  "+",
  // Numeric ranges: digits/commas only, nothing to translate.
  "500,000 - 1,000,000",
  "1,000,000 - 2,000,000",
  // The Arabic brand wordmark, shown as-is in both locales.
  "السوق المفتوح",
  // Language-switcher labels always name the *target* language, so the
  // English UI's "switch to Arabic" label is itself written in Arabic —
  // same string appears in both locale files by design.
  "التبديل الي اللغة العربية",
  "العربية",
]);

function flatten(obj, prefix = "") {
  const out = {};
  for (const [key, value] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}.${key}` : key;
    if (value !== null && typeof value === "object" && !Array.isArray(value)) {
      Object.assign(out, flatten(value, fullKey));
    } else {
      out[fullKey] = value;
    }
  }
  return out;
}

function loadFlat(filePath) {
  const raw = readFileSync(filePath, "utf8");
  return flatten(JSON.parse(raw));
}

const en = loadFlat(EN_PATH);
const ar = loadFlat(AR_PATH);

const enKeys = new Set(Object.keys(en));
const arKeys = new Set(Object.keys(ar));

const missingInAr = [...enKeys].filter((k) => !arKeys.has(k)).sort();
const missingInEn = [...arKeys].filter((k) => !enKeys.has(k)).sort();

const identicalValues = [];
for (const key of enKeys) {
  if (!arKeys.has(key)) continue;
  const enVal = en[key];
  const arVal = ar[key];
  if (typeof enVal !== "string" || typeof arVal !== "string") continue;
  if (enVal === arVal && !ALLOWLIST.has(enVal)) {
    identicalValues.push({ key, value: enVal });
  }
}

let failed = false;

console.log(`en.json: ${enKeys.size} keys`);
console.log(`ar.json: ${arKeys.size} keys`);

if (missingInAr.length > 0) {
  failed = true;
  console.error(`\nMissing in ar.json (${missingInAr.length}):`);
  for (const k of missingInAr) console.error(`  - ${k}`);
}

if (missingInEn.length > 0) {
  failed = true;
  console.error(`\nMissing in en.json (${missingInEn.length}):`);
  for (const k of missingInEn) console.error(`  - ${k}`);
}

if (identicalValues.length > 0) {
  failed = true;
  console.error(
    `\nIdentical en/ar values, not in ALLOWLIST (${identicalValues.length}):`,
  );
  for (const { key, value } of identicalValues) {
    console.error(`  - ${key}: "${value}"`);
  }
}

if (failed) {
  console.error("\nLocale parity check FAILED.");
  process.exit(1);
}

console.log("\nLocale parity check passed: identical key sets, no unexplained duplicate values.");
process.exit(0);
