import { expect, test } from "@playwright/test";
import bn from "../messages/bn.json" with { type: "json" };

test("home page renders the shop chrome", async ({ page }) => {
  await page.goto("/");
  await expect(page.locator("header").first()).toBeVisible();
  await expect(page.locator("article").first()).toBeVisible();
});

test("search box suggests products while typing", async ({ page, isMobile }) => {
  await page.goto(isMobile ? "/search" : "/");
  const box = page.getByRole("combobox").first();
  await box.click();
  await box.fill("ator");

  const list = page.getByRole("listbox");
  await expect(list).toBeVisible();
  await expect(list.getByRole("option").first()).toBeVisible();

  await box.press("Enter");
  await expect(page).toHaveURL(/\/search\?q=ator/);
  await expect(page.locator("article").first()).toBeVisible();
});

test("a misspelt search falls back to the corrected word", async ({ page }) => {
  await page.goto("/search?q=tasbeh");
  const notice = bn.listing.correctedTo.split("{term}")[1].split("{corrected}")[0].replace(/[“”]/g, "").trim();
  await expect(page.getByText(notice, { exact: false })).toBeVisible();
  await expect(page.locator("article").first()).toBeVisible();
});
