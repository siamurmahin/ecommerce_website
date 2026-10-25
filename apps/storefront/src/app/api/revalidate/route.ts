import { timingSafeEqual } from "node:crypto";
import { revalidateTag } from "next/cache";

// Called by the Laravel backend after an admin save (App\Support\StorefrontCache) with the cache tags
// that went stale, e.g. {"tags": ["settings"]}. Tags match the cacheTag() calls in lib/api/*.ts.

function authorized(header: string | null) {
  const secret = process.env.REVALIDATE_SECRET;
  if (!secret || !header) return false;
  const [a, b] = [Buffer.from(header), Buffer.from(secret)];
  return a.length === b.length && timingSafeEqual(a, b);
}

export async function POST(request: Request) {
  if (!authorized(request.headers.get("x-revalidate-secret"))) {
    return Response.json({ ok: false }, { status: 401 });
  }

  const body: unknown = await request.json().catch(() => null);
  const raw = body && typeof body === "object" && "tags" in body && Array.isArray(body.tags) ? body.tags : [];
  const tags = raw.filter((t): t is string => typeof t === "string" && t.length > 0 && t.length <= 256).slice(0, 100);

  if (tags.length === 0) {
    return Response.json({ ok: false, message: "No tags" }, { status: 422 });
  }

  // expire: 0 → the next visit fetches fresh data instead of one more stale copy, so admins see their change at once.
  for (const tag of tags) revalidateTag(tag, { expire: 0 });

  return Response.json({ ok: true, revalidated: tags });
}
