// Screenshots of the ready-made landing designs and of every block, shown in the admin's design and block pickers.
// Needs the backend (with `php artisan db:seed --class=LandingPreviewSeeder`) and the storefront running:
//   pnpm landing:previews            (PREVIEW_BASE_URL=http://127.0.0.1:3000 by default)
// Writes JPEGs to apps/backend/public/images/landing-previews/{designs,blocks}/.
import { mkdir } from "node:fs/promises";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { chromium } from "@playwright/test";

const base = process.env.PREVIEW_BASE_URL ?? "http://127.0.0.1:3000";
const out = join(dirname(fileURLToPath(import.meta.url)), "../../backend/public/images/landing-previews");
const designs = ["classic", "offer", "minimal", "story", "quick", "bundle", "showcase", "elegant"];
const blocks = ["announcement", "hero", "trust", "offer", "notice", "checklist", "benefits", "features", "gallery", "video", "text", "reviews", "faq", "cta", "call", "order_form"];

const width = 1280;
// Hide what isn't part of the design: the dev-mode badge, the mobile order bar, and live video embeds.
const css = "nextjs-portal{display:none!important} iframe{visibility:hidden}";

async function open(context, slug) {
  const page = await context.newPage();
  await page.goto(`${base}/l/${slug}`, { waitUntil: "networkidle", timeout: 120_000 });
  await page.locator('[data-block="order_form"]').waitFor({ timeout: 120_000 });
  await page.addStyleTag({ content: css });
  // Let lazy images load all the way down.
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 120));
    }
    window.scrollTo(0, 0);
  });
  await page.waitForLoadState("networkidle");
  await page.waitForTimeout(800);
  return page;
}

const browser = await chromium.launch();
// Half scale keeps files small; reduced motion skips the scroll-in animations.
const context = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 0.5, reducedMotion: "reduce", locale: "bn-BD" });

await mkdir(join(out, "designs"), { recursive: true });
await mkdir(join(out, "blocks"), { recursive: true });

for (const key of designs) {
  const page = await open(context, `preview-${key}`);
  const height = Math.min(1800, await page.evaluate(() => document.body.scrollHeight));
  await page.screenshot({ path: join(out, "designs", `${key}.jpg`), type: "jpeg", quality: 78, fullPage: true, clip: { x: 0, y: 0, width, height } });
  await page.close();
  console.log(`design ${key}`);
}

const page = await open(context, "preview-blocks");
for (const type of blocks) {
  const box = await page.locator(`[data-block="${type}"]`).first().boundingBox();
  if (!box) {
    console.warn(`block ${type}: not on the page`);
    continue;
  }
  // Thin strips get some of the page around them so the preview fills its frame and shows where they sit:
  // the header and hero top under the announcement bar, the hero's bottom above the trust row.
  const above = type === "announcement" ? 0 : type === "trust" ? 380 : 32;
  const below = type === "announcement" ? 560 : 32;
  const y = Math.max(0, box.y - above);
  await page.screenshot({
    path: join(out, "blocks", `${type}.jpg`),
    type: "jpeg",
    quality: 78,
    fullPage: true,
    clip: { x: 0, y, width, height: Math.min(1000, box.y - y + box.height + below) },
  });
  console.log(`block ${type}`);
}

await browser.close();
