import { expect, type APIRequestContext, type Page } from "@playwright/test";

export type Priority = "low" | "medium" | "high";

export interface CreatedNote {
  id: number;
  title: string;
  version: number;
}

/** A title no other test run will produce, so specs can share one database safely. */
export function uniqueTitle(label: string): string {
  return `${label} ${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
}

/** Seeds a note through the API, which is faster and more targeted than clicking through the UI. */
export async function createNoteViaApi(request: APIRequestContext, title: string, priority: Priority, content = ""): Promise<CreatedNote> {
  const response = await request.post("/api/notes", { data: { title, content, priority } });
  expect(response.status()).toBe(201);
  const body = (await response.json()) as { data: CreatedNote };
  return body.data;
}

/** The sheet for a note on the wall: an article named by the note's (unique) title. */
export function noteCard(page: Page, title: string) {
  return page.getByRole("article", { name: title, exact: true });
}

export function priorityChip(page: Page, name: "High" | "Medium" | "Low") {
  return page.getByRole("group", { name: "Filter by priority" }).getByRole("button", { name, exact: true });
}

export function sortButton(page: Page, name: "Newest" | "Oldest" | "Priority") {
  return page.getByRole("group", { name: "Sort notes" }).getByRole("button", { name, exact: true });
}

export function quickNote(page: Page) {
  return page.getByRole("form", { name: "Quick note" });
}

/** The archive link under the wall, "Archived notes (N)". */
export function archiveLink(page: Page) {
  return page.getByRole("link", { name: /^Archived notes/ });
}

/** Titles of the sheets in document order. With one column (a narrow viewport) that is the list order. */
export async function sheetTitles(page: Page): Promise<string[]> {
  return page.getByRole("article").evaluateAll((sheets) => sheets.map((sheet) => sheet.getAttribute("aria-label") ?? ""));
}
