import type { Note, NoteInput } from "@/domain/note";
import type { Priority } from "@/domain/priority";
import type { NoteSort } from "@/domain/sort";
import { request, requestEnvelope } from "./http";

export { ApiError } from "./http";

export interface ListOptions {
  priorities?: readonly Priority[];
  /** true lists the archived notes instead of the active ones. */
  archived?: boolean;
  /** Words that must all occur in the title or content; blank = no search. */
  search?: string;
  sort?: NoteSort;
}

/** How many notes each list holds, ignoring any filter or search. */
export interface NoteCounts {
  active: number;
  archived: number;
}

export interface NoteList {
  notes: Note[];
  counts: NoteCounts;
}

/** Thin typed client for the notes API. Every call acts on the logged-in user's notes only. */
export const notesApi = {
  async list(
    { priorities = [], archived = false, search = "", sort = "newest" }: ListOptions = {},
    signal?: AbortSignal
  ): Promise<NoteList> {
    const query = new URLSearchParams();
    if (priorities.length > 0) query.set("priority", priorities.join(","));
    if (archived) query.set("archived", "true");
    if (search.trim()) query.set("q", search.trim());
    if (sort !== "newest") query.set("sort", sort);
    const qs = query.toString();
    const { data, meta } = await requestEnvelope<Note[]>("GET", `/api/notes${qs ? `?${qs}` : ""}`, undefined, signal);
    return { notes: data, counts: toCounts(meta.counts) };
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

function toCounts(raw: unknown): NoteCounts {
  const value = (raw ?? {}) as Partial<Record<keyof NoteCounts, unknown>>;
  const count = (n: unknown) => (typeof n === "number" && Number.isInteger(n) && n >= 0 ? n : 0);
  return { active: count(value.active), archived: count(value.archived) };
}
