/** Priorities in display order, highest first. Values match the API's `priority` field. */
export const PRIORITIES = ["high", "medium", "low"] as const;

export type Priority = (typeof PRIORITIES)[number];

export const PRIORITY_LABELS: Record<Priority, string> = {
  high: "High",
  medium: "Medium",
  low: "Low",
};

export function isPriority(value: unknown): value is Priority {
  return typeof value === "string" && (PRIORITIES as readonly string[]).includes(value);
}

/**
 * Reads the list filter from the URL query (`?priority=high,low`). Unknown values are
 * dropped rather than failing, so a hand-edited or outdated link still opens the list.
 * Result is deduplicated and in display order.
 */
export function parsePriorityFilter(raw: unknown): Priority[] {
  const text = Array.isArray(raw) ? raw.join(",") : typeof raw === "string" ? raw : "";
  const wanted = new Set(text.split(",").map((part) => part.trim()));
  return PRIORITIES.filter((priority) => wanted.has(priority));
}

/** The inverse of parsePriorityFilter; `undefined` removes the parameter from the URL. */
export function serializePriorityFilter(priorities: readonly Priority[]): string | undefined {
  const ordered = PRIORITIES.filter((priority) => priorities.includes(priority));
  return ordered.length === 0 || ordered.length === PRIORITIES.length ? undefined : ordered.join(",");
}
