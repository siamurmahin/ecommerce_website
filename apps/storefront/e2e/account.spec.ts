import { expect, test } from "@playwright/test";
import bn from "../messages/bn.json" with { type: "json" };

test("guests see the phone login form, with no console errors", async ({ page }) => {
  const errors: string[] = [];
  page.on("pageerror", (e) => errors.push(e.message));
  page.on("console", (m) => m.type() === "error" && errors.push(m.text()));

  await page.goto("/account");
  await expect(page.getByRole("button", { name: bn.account.sendCode })).toBeVisible();
  expect(errors).toEqual([]);
});

test("product pages hydrate without errors", async ({ page }) => {
  const errors: string[] = [];
  page.on("pageerror", (e) => errors.push(e.message));

  await page.goto(`/p/${process.env.E2E_PRODUCT ?? "oud-attar"}`);
  await expect(page.getByRole("button", { name: bn.product.buyNow })).toBeEnabled();
  await page.waitForLoadState("networkidle");
  expect(errors).toEqual([]);
});
