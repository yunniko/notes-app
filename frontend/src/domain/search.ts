/** Mirrors the API limit (NoteValidator::SEARCH_MAX_LENGTH), so the box cannot produce a 422. */
export const SEARCH_MAX_LENGTH = 100;

/**
 * Reads the search from the URL query (`?q=budget`). A repeated parameter uses the first value;
 * the text is trimmed and cut to the API limit, so a hand-edited link still opens the list.
 */
export function parseSearch(raw: unknown): string {
  const first = Array.isArray(raw) ? raw[0] : raw;
  return typeof first === "string" ? first.trim().slice(0, SEARCH_MAX_LENGTH).trim() : "";
}

/** The inverse of parseSearch; `undefined` removes the parameter from the URL. */
export function serializeSearch(text: string): string | undefined {
  const search = parseSearch(text);
  return search === "" ? undefined : search;
}

/** What an empty list says, given what narrowed it. */
export function emptyListMessage(options: { archived: boolean; search: string; filtered: boolean }): string | null {
  const { archived, search, filtered } = options;
  const where = archived ? "archived notes" : "notes";
  if (search && filtered) return `No ${where} with this priority match “${search}”.`;
  if (search) return `No ${where} match “${search}”.`;
  if (filtered) return archived ? "No archived notes with this priority." : "No notes with this priority.";
  return null; // nothing narrows the list: it is simply empty
}
