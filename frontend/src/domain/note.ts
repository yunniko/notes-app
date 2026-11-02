import type { Priority } from "./priority";

/** A note as the API returns it. Timestamps are ISO 8601 in UTC. */
export interface Note {
  id: number;
  title: string;
  content: string;
  priority: Priority;
  /** Increments on every update; sent back on save to detect concurrent edits. */
  version: number;
  createdAt: string;
  updatedAt: string;
  /** When the note was archived; null for an active note. */
  archivedAt: string | null;
}

/** The editable fields of a note. */
export interface NoteInput {
  title: string;
  content: string;
  priority: Priority;
}

export type FieldErrors = Partial<Record<keyof NoteInput, string>>;

/** Same limits as the backend's NoteValidator; the server stays the authority. */
export const TITLE_MAX_LENGTH = 200;
export const CONTENT_MAX_LENGTH = 20_000;

/**
 * Client-side checks for instant feedback before a round trip. Lengths count Unicode
 * code points, like PHP's mb_strlen, so "ř" or an emoji counts as one character on both sides.
 */
export function validateNoteInput(input: NoteInput): FieldErrors {
  const errors: FieldErrors = {};
  const title = input.title.trim();
  if (title === "") {
    errors.title = "Title is required.";
  } else if (characterCount(title) > TITLE_MAX_LENGTH) {
    errors.title = `Title must be at most ${TITLE_MAX_LENGTH} characters.`;
  }
  if (characterCount(input.content) > CONTENT_MAX_LENGTH) {
    errors.content = `Content must be at most ${CONTENT_MAX_LENGTH} characters.`;
  }
  return errors;
}

export function characterCount(text: string): number {
  return Array.from(text).length;
}
