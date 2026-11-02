import type { RouteLocationRaw } from "vue-router";

export type ListName = "notes" | "archive";

// Module-level, like useFlash: the list view records where it was, the note page links back there.
const lastPath: Partial<Record<ListName, string>> = {};

/**
 * Remembers the list's full URL (tab, priority filter, search), so "back" from a note returns
 * to the same view instead of an unfiltered list.
 */
export function useListMemory() {
  function remember(list: ListName, fullPath: string): void {
    lastPath[list] = fullPath;
  }

  function backTo(list: ListName): RouteLocationRaw {
    return lastPath[list] ?? { name: list };
  }

  return { remember, backTo };
}

export function resetListMemoryForTests(): void {
  delete lastPath.notes;
  delete lastPath.archive;
}
