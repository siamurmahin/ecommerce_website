import { cookies } from "next/headers";
import type { NextRequest } from "next/server";
import { API_URL } from "@/lib/api/client";

// Customer-account calls go through here so the login token lives in an httpOnly cookie the browser
// scripts can't read. /api/customer/me → GET {API}/customer, /api/customer/orders → {API}/customer/orders…
// The verify response's token is swapped for the cookie; logout (or an expired token) clears it.

const COOKIE = "lj_customer";
const MAX_AGE = 60 * 24 * 60 * 60; // matches the backend token lifetime

async function handle(request: NextRequest, { params }: RouteContext<"/api/customer/[...path]">) {
  const { path } = await params;
  const sub = path.map(encodeURIComponent).join("/");
  const target = new URL(`${API_URL}/customer${sub === "me" ? "" : `/${sub}`}`);
  request.nextUrl.searchParams.forEach((value, key) => target.searchParams.set(key, value));

  const jar = await cookies();
  const token = jar.get(COOKIE)?.value;
  // Signed-out visitors: answer account calls here instead of a round trip to the API. "Who am I" is asked on
  // every page, so it answers null rather than a 401 that browsers log as a console error.
  const whoAmI = sub === "me" && request.method === "GET";
  if (!token && whoAmI) return Response.json({ data: null });
  if (!token && sub !== "otp" && sub !== "verify") return Response.json({ message: "Unauthenticated." }, { status: 401 });
  const headers = new Headers({ Accept: "application/json" });
  const type = request.headers.get("content-type");
  if (type) headers.set("Content-Type", type);
  if (token) headers.set("Authorization", `Bearer ${token}`);

  // The backend rate-limits login codes per visitor; vouch for the real IP with the shared secret.
  const ip = request.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || request.headers.get("x-real-ip");
  const secret = process.env.REVALIDATE_SECRET;
  if (ip && secret) {
    headers.set("X-Client-IP", ip);
    headers.set("X-Storefront-Key", secret);
  }

  const res = await fetch(target, {
    method: request.method,
    headers,
    body: request.method === "GET" ? undefined : await request.arrayBuffer(),
    cache: "no-store",
  });

  if (sub === "verify" && res.ok) {
    const { token: issued, customer } = (await res.json()) as { token: string; customer: unknown };
    jar.set(COOKIE, issued, { httpOnly: true, secure: process.env.NODE_ENV === "production", sameSite: "lax", path: "/", maxAge: MAX_AGE });
    return Response.json({ customer });
  }

  if (sub === "logout" || res.status === 401) jar.delete(COOKIE);
  if (whoAmI && res.status === 401) return Response.json({ data: null });
  if (res.status === 204) return new Response(null, { status: 204 });

  return new Response(res.body, { status: res.status, headers: { "Content-Type": res.headers.get("content-type") ?? "application/json" } });
}

export { handle as GET, handle as POST, handle as PUT, handle as DELETE };
