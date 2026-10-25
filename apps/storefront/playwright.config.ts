import { defineConfig, devices } from "@playwright/test";

/**
 * End-to-end tests against a running storefront + backend.
 * Locally: `pnpm build && pnpm start --port 3100`, backend on :8000, then `pnpm e2e`.
 * Use 127.0.0.1, not localhost: another dev server on [::1]:3000 can shadow Next on macOS.
 */
export default defineConfig({
  testDir: "./e2e",
  timeout: 45_000,
  expect: { timeout: 10_000 },
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [["github"], ["html", { open: "never" }]] : "list",
  use: {
    baseURL: process.env.E2E_BASE_URL ?? "http://127.0.0.1:3100",
    locale: "bn-BD",
    trace: "retain-on-failure",
  },
  projects: [
    { name: "desktop", use: { ...devices["Desktop Chrome"] } },
    { name: "mobile", use: { ...devices["Pixel 7"] } },
  ],
});
