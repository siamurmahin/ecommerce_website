import { expect, test } from "@playwright/test";
import bn from "../messages/bn.json" with { type: "json" };

// A reserved test number; local cleanup: delete orders with note "E2E test order".
const PHONE = process.env.E2E_PHONE ?? "01700000001";
const PRODUCT = process.env.E2E_PRODUCT ?? "oud-attar";

test("guest places a cash-on-delivery order", async ({ page }) => {
  await page.goto(`/p/${PRODUCT}`);
  await page.getByRole("button", { name: bn.product.buyNow }).click();
  await expect(page).toHaveURL(/\/checkout/);

  await page.getByLabel(bn.checkout.name).fill("E2E Tester");
  await page.getByLabel(bn.checkout.phone).fill(PHONE);

  const district = page.getByLabel(bn.checkout.district);
  await district.selectOption({ index: 1 });
  const thana = page.getByLabel(bn.checkout.thana);
  if (await thana.isVisible().catch(() => false)) {
    await expect(thana.locator("option")).not.toHaveCount(1);
    await thana.selectOption({ index: 1 });
  }

  await page.getByLabel(bn.checkout.fullAddress).fill("House 1, Road 2, Test Area");
  await page.getByLabel(bn.checkout.note).fill("E2E test order");
  await page.getByRole("button", { name: bn.checkout.placeOrder }).click();

  await expect(page).toHaveURL(/\/order\/[^/?]+\?token=/, { timeout: 20_000 });
  await expect(page.getByText(bn.order.number)).toBeVisible();
});

test("checkout rejects an invalid phone number", async ({ page }) => {
  await page.goto(`/p/${PRODUCT}`);
  await page.getByRole("button", { name: bn.product.buyNow }).click();
  await expect(page).toHaveURL(/\/checkout/);

  await page.getByLabel(bn.checkout.name).fill("E2E Tester");
  await page.getByLabel(bn.checkout.phone).fill("0123");
  await page.getByRole("button", { name: bn.checkout.placeOrder }).click();

  await expect(page.getByText(bn.checkout.errors.phone)).toBeVisible();
  await expect(page).toHaveURL(/\/checkout/);
});
