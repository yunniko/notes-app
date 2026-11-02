import type { Note } from "@/domain/note";

/**
 * An error response from the API, parsed from its RFC 9457 problem-details body.
 * `fieldErrors` is set for 422, `current` (the note as stored now) for a 409 edit conflict,
 * `retryAfterSeconds` for a 429.
 */
export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly fieldErrors: Record<string, string> = {},
    readonly current?: Note,
    readonly retryAfterSeconds?: number
  ) {
    super(message);
    this.name = "ApiError";
  }

  get isConflict(): boolean {
    return this.status === 409 && this.current !== undefined;
  }
}

let onUnauthorized: (() => void) | undefined;

/**
 * Called when a request outside /api/auth/ answers 401: the session ended (expired, or logged
 * out in another tab). Auth endpoints are excluded because 401 is their normal "wrong password"
 * or "not logged in" answer, which their callers handle themselves.
 */
export function setUnauthorizedHandler(handler: (() => void) | undefined): void {
  onUnauthorized = handler;
}

/** Sends a JSON request to the same-origin API and unwraps `{ data }`. A 204 resolves to undefined. */
export async function request<T>(method: string, url: string, body?: unknown, signal?: AbortSignal): Promise<T> {
  let response: Response;
  try {
    response = await fetch(url, {
      method,
      signal,
      headers: body === undefined ? { Accept: "application/json" } : { Accept: "application/json", "Content-Type": "application/json" },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") throw error;
    throw new ApiError("Cannot reach the server. Check your connection and try again.", 0);
  }

  if (response.status === 204) return undefined as T;

  const payload: unknown = await response.json().catch(() => null);
  if (!response.ok) {
    if (response.status === 401 && !url.startsWith("/api/auth/")) onUnauthorized?.();
    throw toApiError(response, payload);
  }
  if (!isRecord(payload) || !("data" in payload)) {
    throw new ApiError("The server sent an unexpected response.", response.status);
  }
  return payload.data as T;
}

function toApiError(response: Response, payload: unknown): ApiError {
  const { status } = response;
  const retryAfter = Number(response.headers.get("Retry-After"));
  const retryAfterSeconds = Number.isInteger(retryAfter) && retryAfter > 0 ? retryAfter : undefined;
  if (!isRecord(payload)) {
    return new ApiError(`The server responded with an error (${status}).`, status, {}, undefined, retryAfterSeconds);
  }
  const message = typeof payload.detail === "string" ? payload.detail : `The server responded with an error (${status}).`;
  const fieldErrors = isRecord(payload.errors) ? (payload.errors as Record<string, string>) : {};
  const current = isRecord(payload.current) ? (payload.current as unknown as Note) : undefined;
  return new ApiError(message, status, fieldErrors, current, retryAfterSeconds);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
