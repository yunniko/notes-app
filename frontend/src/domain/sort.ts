/** List orders, matching the API's `sort` parameter. "newest" is the default and stays out of the URL. */
export const NOTE_SORTS = ["newest", "oldest", "priority"] as const;

export type NoteSort = (typeof NOTE_SORTS)[number];

export const SORT_LABELS: Record<NoteSort, string> = {
  newest: "Newest",
  oldest: "Oldest",
  priority: "Priority",
};

/** Reads `?sort=` from the URL; anything unknown falls back to newest, so an old link still opens. */
export function parseSort(raw: unknown): NoteSort {
  const first: unknown = Array.isArray(raw) ? raw[0] : raw;
  return (NOTE_SORTS as readonly unknown[]).includes(first) ? (first as NoteSort) : "newest";
}

/** The inverse of parseSort; `undefined` removes the parameter from the URL. */
export function serializeSort(sort: NoteSort): string | undefined {
  return sort === "newest" ? undefined : sort;
}
