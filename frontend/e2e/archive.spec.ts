import { expect, test } from "./helpers/auth";
import { archiveLink, createNoteViaApi, noteCard, priorityChip, quickNote, uniqueTitle } from "./helpers/notes";

test.describe("archiving", () => {
  test("an archived note leaves the wall, shows in the archive, and can be restored", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Old plan"), "low");
    const other = await createNoteViaApi(page.request, uniqueTitle("Still current"), "high");
    await page.goto("/");
    // An empty archive has no link to it.
    await expect(noteCard(page, other.title)).toBeVisible();
    await expect(archiveLink(page)).toHaveCount(0);

    await noteCard(page, note.title).getByRole("button", { name: "Archive" }).click();
    await expect(page.getByText("Note archived.")).toBeVisible();
    await expect(noteCard(page, note.title)).toHaveCount(0);
    await expect(noteCard(page, other.title)).toBeVisible();
    await expect(archiveLink(page)).toHaveText("Archived notes (1)");

    await archiveLink(page).click();
    await expect(page).toHaveURL("/archive");
    await expect(page.getByRole("heading", { name: "Archive", exact: true })).toBeVisible();
    await expect(noteCard(page, note.title)).toContainText("Archived today");
    await expect(noteCard(page, other.title)).toHaveCount(0);
    // The archive is for finding, not writing.
    await expect(quickNote(page)).toHaveCount(0);

    // The priority filter works in the archive too, and carries over between the two.
    await priorityChip(page, "High").click();
    await expect(page).toHaveURL("/archive?priority=high");
    await expect(page.getByText("No archived notes with this priority.")).toBeVisible();
    await page.getByRole("button", { name: "Show all priorities" }).click();

    await noteCard(page, note.title).getByRole("button", { name: "Restore" }).click();
    await expect(page.getByText("Note restored.")).toBeVisible();
    await expect(page.getByText("Nothing archived.")).toBeVisible();

    await page.getByRole("link", { name: "← Back to notes" }).click();
    await expect(page).toHaveURL("/");
    await expect(noteCard(page, note.title)).toBeVisible();
    await expect(archiveLink(page)).toHaveCount(0);
  });

  test("an archived note can still be edited in place", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Reference"), "medium");
    expect((await page.request.post(`/api/notes/${note.id}/archive`)).status()).toBe(200);
    await page.goto("/archive");

    await noteCard(page, note.title).getByRole("button", { name: "Edit" }).click();
    const form = page.getByRole("form", { name: "Edit note" });
    await form.getByLabel("Title").fill(`${note.title} v2`);
    await form.getByRole("button", { name: "Save" }).click();

    await expect(page.getByText("Note saved.")).toBeVisible();
    await expect(page).toHaveURL("/archive");
    await expect(noteCard(page, `${note.title} v2`)).toContainText("Archived");
  });
});

test.describe("deleting", () => {
  test("delete asks first; cancelling keeps the note, confirming removes it for good", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Throwaway"), "low");
    await page.goto("/");
    const card = noteCard(page, note.title);

    await card.getByRole("button", { name: "Delete" }).click();
    const dialog = page.getByRole("dialog", { name: "Delete this note?" });
    await expect(dialog).toContainText(note.title);
    // The confirmation sits in the middle of the viewport.
    const box = (await dialog.boundingBox())!;
    const viewport = page.viewportSize()!;
    expect(Math.abs(box.x + box.width / 2 - viewport.width / 2)).toBeLessThan(2);
    expect(Math.abs(box.y + box.height / 2 - viewport.height / 2)).toBeLessThan(2);
    await dialog.getByRole("button", { name: "Cancel" }).click();
    await expect(dialog).toBeHidden();
    await expect(card).toBeVisible();

    await card.getByRole("button", { name: "Delete" }).click();
    await page.keyboard.press("Escape");
    await expect(dialog).toBeHidden();
    await expect(card).toBeVisible();

    await card.getByRole("button", { name: "Delete" }).click();
    await dialog.getByRole("button", { name: "Delete permanently" }).click();
    await expect(page.getByText("Note deleted.")).toBeVisible();
    await expect(card).toHaveCount(0);

    expect((await page.request.get(`/api/notes/${note.id}`)).status()).toBe(404);
    await page.reload();
    await expect(noteCard(page, note.title)).toHaveCount(0);
  });

  test("a note deleted elsewhere just leaves the wall", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Gone already"), "medium");
    await page.goto("/");
    await expect(noteCard(page, note.title)).toBeVisible();
    expect((await page.request.delete(`/api/notes/${note.id}`)).status()).toBe(204);

    await noteCard(page, note.title).getByRole("button", { name: "Archive" }).click();

    await expect(page.getByRole("alert")).toContainText("no longer exists");
    await expect(noteCard(page, note.title)).toHaveCount(0);
  });
});
