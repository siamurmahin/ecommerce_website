export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    /** Parsed JSON body, e.g. Laravel's `{ message, errors }` on 422. */
    public data: unknown = null,
  ) {
    super(message);
  }

  /** Field → first message, for Laravel validation responses. */
  get fieldErrors(): Record<string, string> {
    const errors = (this.data as { errors?: Record<string, string[]> } | null)?.errors ?? {};
    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]));
  }
}

type ApiInit = Omit<RequestInit, "body"> & { locale?: string; json?: unknown };

/** Fetch against the Laravel API (`/api/v1`). Response types come from @looks-jhorna/api-types. */
export async function api<T>(path: string, init?: ApiInit): Promise<T> {
  const { locale, json, ...rest } = init ?? {};
  const url = new URL(`${API_URL}${path}`);
  if (locale) url.searchParams.set("locale", locale);

  const res = await fetch(url, {
    ...rest,
    method: rest.method ?? (json === undefined ? "GET" : "POST"),
    body: json === undefined ? undefined : JSON.stringify(json),
    headers: {
      Accept: "application/json",
      ...(json === undefined ? {} : { "Content-Type": "application/json" }),
      ...rest.headers,
    },
  });

  if (!res.ok) {
    const data = await res.json().catch(() => null);
    throw new ApiError(res.status, `API ${res.status} on ${path}`, data);
  }

  return res.json() as Promise<T>;
}

/** Like `api`, but a 404 resolves to null so pages can call notFound(). */
export async function apiOrNull<T>(path: string, init?: ApiInit): Promise<T | null> {
  try {
    return await api<T>(path, init);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}
