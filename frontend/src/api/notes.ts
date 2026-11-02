import type { Note, NoteInput } from "@/domain/note";
import type { Priority } from "@/domain/priority";
import { request } from "./http";

export { ApiError } from "./http";

export interface ListOptions {
  priorities?: readonly Priority[];
  /** true lists the archived notes instead of the active ones. */
  archived?: boolean;
}

/** Thin typed client for the notes API. Every call acts on the logged-in user's notes only. */
export const notesApi = {
  list({ priorities = [], archived = false }: ListOptions = {}, signal?: AbortSignal): Promise<Note[]> {
    const query = new URLSearchParams();
    if (priorities.length > 0) query.set("priority", priorities.join(","));
    if (archived) query.set("archived", "true");
    const search = query.toString();
    return request<Note[]>("GET", `/api/notes${search ? `?${search}` : ""}`, undefined, signal);
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

  /** Idempotent: archiving an archived note returns it unchanged. */
  archive(id: number): Promise<Note> {
    return request<Note>("POST", `/api/notes/${id}/archive`);
  },

  restore(id: number): Promise<Note> {
    return request<Note>("POST", `/api/notes/${id}/restore`);
  },

  /** Permanent; the UI confirms first. */
  delete(id: number): Promise<void> {
    return request<void>("DELETE", `/api/notes/${id}`);
  },
};
