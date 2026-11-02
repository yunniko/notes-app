import type { FieldErrors, Note, NoteInput } from "@/domain/note";
import type { Priority } from "@/domain/priority";

/**
 * An error response from the API, parsed from its RFC 9457 problem-details body.
 * `fieldErrors` is set for 422, `current` (the note as stored now) for a 409 edit conflict.
 */
export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly fieldErrors: FieldErrors = {},
    readonly current?: Note
  ) {
    super(message);
    this.name = "ApiError";
  }

  get isConflict(): boolean {
    return this.status === 409 && this.current !== undefined;
  }
}

/** Thin typed client for the notes API. All requests go to the same origin under /api. */
export const notesApi = {
  list(priorities: readonly Priority[] = [], signal?: AbortSignal): Promise<Note[]> {
    const query = priorities.length > 0 ? `?priority=${encodeURIComponent(priorities.join(","))}` : "";
    return request<Note[]>("GET", `/api/notes${query}`, undefined, signal);
  },

  get(id: number, signal?: AbortSignal): Promise<Note> {
    return request<Note>("GET", `/api/notes/${id}`, undefined, signal);
  },

  create(input: NoteInput): Promise<Note> {
    return request<Note>("POST", "/api/notes", input);
  },

  /** `version` is the one the user started editing from; a stale one rejects with a 409 ApiError. */
  update(id: number, input: NoteInput, version: number): Promise<Note> {
    return request<Note>("PUT", `/api/notes/${id}`, { ...input, version });
  },
};

async function request<T>(method: string, url: string, body?: unknown, signal?: AbortSignal): Promise<T> {
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

  const payload: unknown = await response.json().catch(() => null);
  if (!response.ok) {
    throw toApiError(response.status, payload);
  }
  if (!isRecord(payload) || !("data" in payload)) {
    throw new ApiError("The server sent an unexpected response.", response.status);
  }
  return payload.data as T;
}

function toApiError(status: number, payload: unknown): ApiError {
  if (!isRecord(payload)) {
    return new ApiError(`The server responded with an error (${status}).`, status);
  }
  const message = typeof payload.detail === "string" ? payload.detail : `The server responded with an error (${status}).`;
  const fieldErrors = isRecord(payload.errors) ? (payload.errors as FieldErrors) : {};
  const current = isRecord(payload.current) ? (payload.current as unknown as Note) : undefined;
  return new ApiError(message, status, fieldErrors, current);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
