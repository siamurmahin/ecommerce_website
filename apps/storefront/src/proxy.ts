import createMiddleware from "next-intl/middleware";
import { NextRequest, NextResponse } from "next/server";
import { routing } from "./i18n/routing";
import { API_URL } from "./lib/api/client";

const intl = createMiddleware(routing);

// Landing page A/B tests: a visitor of /l/{main} is shown one of its variants (rewritten, so the URL
// and the ad link stay the same) and keeps seeing it via a cookie. Tests come from the API and are
// re-read at most once a minute.

type Experiments = Record<string, { slug: string; weight: number }[]>;
const TTL = 60_000;
let cached: { at: number; data: Experiments } | null = null;

async function experiments(): Promise<Experiments> {
  if (cached && Date.now() - cached.at < TTL) return cached.data;
  try {
    const res = await fetch(`${API_URL}/landing-pages/experiments`, { headers: { Accept: "application/json" }, cache: "no-store" });
    cached = { at: Date.now(), data: res.ok ? ((await res.json()) as { data: Experiments }).data : {} };
  } catch {
    cached = { at: Date.now(), data: cached?.data ?? {} }; // API down: keep the last split
  }
  return cached.data;
}

function pick(pages: Experiments[string]): string {
  const total = pages.reduce((sum, p) => sum + p.weight, 0);
  let roll = Math.random() * total;
  for (const page of pages) {
    roll -= page.weight;
    if (roll < 0) return page.slug;
  }
  return pages[0].slug;
}

export default async function proxy(request: NextRequest) {
  const match = request.nextUrl.pathname.match(/^(\/en)?\/l\/([^/]+)\/?$/);
  const pages = match ? (await experiments())[decodeURIComponent(match[2])] : undefined;
  if (!match || !pages) return intl(request);

  const main = decodeURIComponent(match[2]);
  const cookie = `lj_ab_${main}`;
  const saved = request.cookies.get(cookie)?.value;
  const chosen = saved && pages.some((p) => p.slug === saved) ? saved : pick(pages);

  let response: NextResponse;
  if (chosen === main) {
    response = intl(request);
  } else {
    const url = request.nextUrl.clone();
    url.pathname = `${match[1] ?? ""}/l/${encodeURIComponent(chosen)}`;
    response = intl(new NextRequest(url, request));
    // next-intl only rewrites when it changes the path itself (default locale); otherwise do it here.
    if (!response.headers.has("x-middleware-rewrite") && !response.headers.has("location")) {
      response = NextResponse.rewrite(url, { headers: response.headers });
    }
  }

  response.cookies.set(cookie, chosen, { maxAge: 60 * 60 * 24 * 30, path: "/", sameSite: "lax" });
  return response;
}

export const config = {
  // Everything except API routes, Next internals and files with an extension.
  matcher: "/((?!api|_next|_vercel|.*\\..*).*)",
};
