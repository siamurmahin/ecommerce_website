import { defineConfig, devices } from "@playwright/test";

/**
 * End-to-end tests against a running storefront + backend.
 * Locally: `pnpm build && pnpm start --port 3100`, backend on :8000, then `pnpm e2e`.
 * Use 127.0.0.1, not localhost: another dev server on [::1]:3000 can shadow Next on macOS.
 */
const baseURL = process.env.E2E_BASE_URL ?? "http://127.0.0.1:3100";

export default defineConfig({
  testDir: "./e2e",
  timeout: 45_000,
  expect: { timeout: 10_000 },
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [["github"], ["html", { open: "never" }]] : "list",
  use: {
    baseURL,
    locale: "bn-BD",
    // The tests read Bangla copy; the shop may send first-time visitors to English (সেটিংস → ভাষা), so pick Bangla like the language switch does.
    storageState: { cookies: [{ name: "lj_lang", value: "bn", domain: new URL(baseURL).hostname, path: "/", expires: -1, httpOnly: false, secure: false, sameSite: "Lax" }], origins: [] },
    trace: "retain-on-failure",
  },
  projects: [
    { name: "desktop", use: { ...devices["Desktop Chrome"] } },
    { name: "mobile", use: { ...devices["Pixel 7"] } },
  ],
});
