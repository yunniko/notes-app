import { expect, test } from "@playwright/test";
import { createNoteViaApi, noteCard, priorityChip, uniqueTitle } from "./helpers/notes";

test.describe("creating a note", () => {
  test("a new note appears in the list with its priority", async ({ page }) => {
    const title = uniqueTitle("Buy train tickets");
    await page.goto("/");

    await page.getByRole("link", { name: "New note" }).click();
    await expect(page.getByRole("heading", { name: "New note" })).toBeVisible();
    await page.getByLabel("Title").fill(title);
    await page.getByLabel("High").check();
    await page.getByLabel("Content (optional)").fill("Prague → Brno\nFriday morning");
    await page.getByRole("button", { name: "Create note" }).click();

    await expect(page).toHaveURL("/");
    await expect(page.getByText("Note created.")).toBeVisible();
    const card = noteCard(page, title);
    await expect(card).toContainText("High");
    await expect(card).toContainText("Prague → Brno");
  });

  test("an empty title is rejected before anything is sent", async ({ page }) => {
    let posted = false;
    page.on("request", (request) => {
      if (request.method() === "POST" && request.url().includes("/api/notes")) posted = true;
    });
    await page.goto("/notes/new");

    await page.getByLabel("Title").fill("   ");
    await page.getByRole("button", { name: "Create note" }).click();

    await expect(page.getByRole("alert")).toHaveText("Title is required.");
    await expect(page.getByLabel("Title")).toBeFocused();
    expect(posted).toBe(false);
  });
});

test.describe("updating a note", () => {
  test("edits are saved and the note moves to the top of the list", async ({ page, request }) => {
    const note = await createNoteViaApi(request, uniqueTitle("Draft agenda"), "low");
    const newer = await createNoteViaApi(request, uniqueTitle("Newer note"), "low");
    await page.goto("/");

    await noteCard(page, note.title).getByRole("link").click();
    await expect(page.getByRole("heading", { name: "Edit note" })).toBeVisible();
    const newTitle = `${note.title} (final)`;
    await page.getByLabel("Title").fill(newTitle);
    await page.getByLabel("Medium").check();
    await page.getByRole("button", { name: "Save changes" }).click();

    await expect(page.getByText("Note saved.")).toBeVisible();
    await expect(noteCard(page, newTitle)).toContainText("Medium"); // also waits for the list to load
    // Most recently updated first: the edited note now sorts above the one created after it.
    const titles = await page.getByRole("article").getByRole("heading").allTextContents();
    expect(titles.indexOf(newTitle)).toBeGreaterThanOrEqual(0);
    expect(titles.indexOf(newTitle)).toBeLessThan(titles.indexOf(newer.title));
  });

  test("a concurrent edit is detected and can be overwritten knowingly", async ({ page, request }) => {
    const note = await createNoteViaApi(request, uniqueTitle("Shared shopping list"), "medium");
    await page.goto(`/notes/${note.id}`);
    await expect(page.getByLabel("Title")).toHaveValue(note.title);

    // Someone else saves first, from another tab or device.
    const theirs = `${note.title} — theirs`;
    const other = await request.put(`/api/notes/${note.id}`, { data: { title: theirs, priority: "high", version: 1 } });
    expect(other.status()).toBe(200);

    const mine = `${note.title} — mine`;
    await page.getByLabel("Title").fill(mine);
    await page.getByRole("button", { name: "Save changes" }).click();

    const banner = page.getByRole("alert").filter({ hasText: "changed somewhere else" });
    await expect(banner).toBeVisible();
    await banner.getByText("Show the other version").click();
    await expect(banner).toContainText(theirs);

    await banner.getByRole("button", { name: "Save mine anyway" }).click();
    await expect(page.getByText("Note saved.")).toBeVisible();
    await expect(noteCard(page, mine)).toBeVisible();
    await expect(noteCard(page, theirs)).toHaveCount(0);
  });

  test("leaving with unsaved changes asks first", async ({ page, request }) => {
    const note = await createNoteViaApi(request, uniqueTitle("Unsaved"), "low");
    await page.goto(`/notes/${note.id}`);
    await page.getByLabel("Title").fill("changed but not saved");

    page.once("dialog", (dialog) => void dialog.dismiss());
    await page.getByRole("button", { name: "Cancel" }).click();
    await expect(page).toHaveURL(`/notes/${note.id}`);

    page.once("dialog", (dialog) => void dialog.accept());
    await page.getByRole("button", { name: "Cancel" }).click();
    await expect(page).toHaveURL("/");
  });
});

test.describe("filtering by priority", () => {
  test("chips filter the list, and the filter lives in the URL", async ({ page, request }) => {
    const high = await createNoteViaApi(request, uniqueTitle("Pay rent"), "high");
    const medium = await createNoteViaApi(request, uniqueTitle("Book dentist"), "medium");
    const low = await createNoteViaApi(request, uniqueTitle("Sort photos"), "low");
    await page.goto("/");

    await priorityChip(page, "High").click();
    await expect(page).toHaveURL("/?priority=high");
    await expect(noteCard(page, high.title)).toBeVisible();
    await expect(noteCard(page, medium.title)).toHaveCount(0);
    await expect(noteCard(page, low.title)).toHaveCount(0);

    await priorityChip(page, "Low").click();
    await expect(page).toHaveURL("/?priority=high,low");
    await expect(noteCard(page, low.title)).toBeVisible();
    await expect(noteCard(page, medium.title)).toHaveCount(0);

    await page.reload();
    await expect(priorityChip(page, "High")).toHaveAttribute("aria-pressed", "true");
    await expect(priorityChip(page, "Low")).toHaveAttribute("aria-pressed", "true");
    await expect(noteCard(page, medium.title)).toHaveCount(0);

    await priorityChip(page, "All").click();
    await expect(page).toHaveURL("/");
    await expect(noteCard(page, medium.title)).toBeVisible();
  });
});

test.describe("bad addresses", () => {
  test("a missing note says so instead of showing an empty form", async ({ page }) => {
    await page.goto("/notes/999999999");
    await expect(page.getByRole("alert")).toContainText("This note does not exist");
  });

  test("an unknown page offers a way back", async ({ page }) => {
    await page.goto("/no/such/page");
    await expect(page.getByRole("heading", { name: "Page not found" })).toBeVisible();
    await page.getByRole("link", { name: "Go to your notes" }).click();
    await expect(page.getByRole("heading", { name: "Notes", exact: true })).toBeVisible();
  });
});
