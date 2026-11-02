import { expect, test } from "./helpers/auth";
import { archiveLink, createNoteViaApi, noteCard, priorityChip, uniqueTitle } from "./helpers/notes";

const searchBox = (page: import("@playwright/test").Page) => page.getByRole("searchbox", { name: "Search notes" });

test.describe("searching", () => {
  test("finds notes by words in title or content, ignoring case and accents, and keeps the search in the URL", async ({ page }) => {
    const cafe = await createNoteViaApi(page.request, uniqueTitle("Café shopping"), "low", "Oat milk and beans");
    const budget = await createNoteViaApi(page.request, uniqueTitle("Budget"), "high", "Review the CAFE costs before Friday");
    const other = await createNoteViaApi(page.request, uniqueTitle("Holiday"), "high", "Book the train");
    await page.goto("/");

    await searchBox(page).fill("cafe");
    await expect(page).toHaveURL("/?q=cafe");
    await expect(noteCard(page, cafe.title)).toBeVisible();
    await expect(noteCard(page, budget.title)).toBeVisible();
    await expect(noteCard(page, other.title)).toHaveCount(0);

    // Every word must match, in any order.
    await searchBox(page).fill("costs cafe");
    await expect(noteCard(page, cafe.title)).toHaveCount(0);
    await expect(noteCard(page, budget.title)).toBeVisible();

    // Combines with the priority filter, and survives a reload.
    await searchBox(page).fill("cafe");
    await priorityChip(page, "Low").click();
    await expect(page).toHaveURL("/?q=cafe&priority=low");
    await page.reload();
    await expect(searchBox(page)).toHaveValue("cafe");
    await expect(noteCard(page, cafe.title)).toBeVisible();
    await expect(noteCard(page, budget.title)).toHaveCount(0);
  });

  test("an empty result says why and offers a way out", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Garden"), "medium", "Plant tulips");
    await page.goto("/?q=zzz-nothing");

    await expect(page.getByText("No notes match “zzz-nothing”.")).toBeVisible();
    await page.getByRole("button", { name: "Clear search", exact: true }).click();

    await expect(page).toHaveURL("/");
    await expect(searchBox(page)).toHaveValue("");
    await expect(noteCard(page, note.title)).toBeVisible();
  });

  test("Escape clears the search", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Escape me"), "low");
    await page.goto("/?q=nothing-like-this");
    await expect(page.getByText(/No notes match/)).toBeVisible();

    await searchBox(page).press("Escape");

    await expect(page).toHaveURL("/");
    await expect(noteCard(page, note.title)).toBeVisible();
  });

  test("the search carries over to the archive and back", async ({ page }) => {
    const active = await createNoteViaApi(page.request, uniqueTitle("Invoice active"), "low");
    const archived = await createNoteViaApi(page.request, uniqueTitle("Invoice archived"), "low");
    expect((await page.request.post(`/api/notes/${archived.id}/archive`)).status()).toBe(200);
    await page.goto("/?q=invoice");
    await expect(noteCard(page, active.title)).toBeVisible();

    await archiveLink(page).click();
    await expect(page).toHaveURL("/archive?q=invoice");
    await expect(searchBox(page)).toHaveValue("invoice");
    await expect(noteCard(page, archived.title)).toBeVisible();
    await expect(noteCard(page, active.title)).toHaveCount(0);

    await page.getByRole("link", { name: "← Back to notes" }).click();
    await expect(page).toHaveURL("/?q=invoice");
    await expect(searchBox(page)).toHaveValue("invoice");
    await expect(noteCard(page, active.title)).toBeVisible();
  });

  test("text that looks like a wildcard is searched literally", async ({ page }) => {
    const percent = await createNoteViaApi(page.request, uniqueTitle("Sale"), "low", "Save 50% today");
    const plain = await createNoteViaApi(page.request, uniqueTitle("Count"), "low", "Save 500 today");
    await page.goto("/");

    await searchBox(page).fill("50%");
    await expect(noteCard(page, percent.title)).toBeVisible();
    await expect(noteCard(page, plain.title)).toHaveCount(0);
  });
});
