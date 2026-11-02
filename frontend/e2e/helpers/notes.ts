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

/** The card for a note in the list, located by its (unique) title link. */
export function noteCard(page: Page, title: string) {
  return page.getByRole("article").filter({ has: page.getByRole("link", { name: title, exact: true }) });
}

export function priorityChip(page: Page, name: "All" | "High" | "Medium" | "Low") {
  return page.getByRole("group", { name: "Filter by priority" }).getByRole("button", { name, exact: true });
}
