import { expect, test } from "./helpers/auth";
import { archiveLink, createNoteViaApi, noteCard, priorityChip, quickNote, sheetTitles, sortButton, uniqueTitle } from "./helpers/notes";

test.describe("the quick note", () => {
  test("a note written in the composer lands on the wall with its priority", async ({ page }) => {
    const title = uniqueTitle("Buy train tickets");
    await page.goto("/");
    const composer = quickNote(page);

    await expect(composer.getByRole("button", { name: "Add note" })).toBeDisabled();
    await composer.getByLabel("Title").fill(title);
    await composer.getByLabel("Content").fill("Prague → Brno\nFriday morning");
    await composer.getByRole("button", { name: "High priority" }).click();
    await expect(composer.getByRole("button", { name: "High priority" })).toHaveAttribute("aria-pressed", "true");
    await composer.getByRole("button", { name: "Add note" }).click();

    await expect(page.getByText("Note created.")).toBeVisible();
    const card = noteCard(page, title);
    await expect(card).toContainText("High");
    await expect(card).toContainText("Friday morning");
    // The composer is empty again, ready for the next one.
    await expect(composer.getByLabel("Title")).toHaveValue("");
    await expect(composer.getByRole("button", { name: "Medium priority" })).toHaveAttribute("aria-pressed", "true");
  });

  test("a title of spaces cannot be added, not even with Enter", async ({ page }) => {
    let posted = false;
    page.on("request", (request) => {
      if (request.method() === "POST" && request.url().includes("/api/notes")) posted = true;
    });
    await page.goto("/");
    const composer = quickNote(page);

    await composer.getByLabel("Title").fill("   ");
    await composer.getByLabel("Content").fill("Some text");
    await expect(composer.getByRole("button", { name: "Add note" })).toBeDisabled();
    await composer.getByLabel("Title").press("Enter");

    await expect(page.getByText("Note created.")).toHaveCount(0);
    expect(posted).toBe(false);
  });

  test("closing the composer brings back New note in the header, which reopens it", async ({ page }) => {
    await page.goto("/");
    const newNote = page.getByRole("banner").getByRole("button", { name: "New note" });
    // While the composer is on screen, the header does not offer a second way to the same place.
    await expect(quickNote(page)).toBeVisible();
    await expect(newNote).toHaveCount(0);

    await quickNote(page).getByRole("button", { name: "Close quick note" }).click();
    await expect(quickNote(page)).toHaveCount(0);
    await newNote.click();

    await expect(quickNote(page).getByLabel("Title")).toBeFocused();
  });

  test("New note from the archive returns to the wall with the composer focused", async ({ page }) => {
    await page.goto("/archive");
    await page.getByRole("banner").getByRole("button", { name: "New note" }).click();

    await expect(page).toHaveURL("/");
    await expect(quickNote(page).getByLabel("Title")).toBeFocused();
  });
});

test.describe("editing on the card", () => {
  test("Edit turns the sheet into a form; Save keeps the changes, Cancel drops them", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Draft agenda"), "low", "First point");
    await page.goto("/");

    await noteCard(page, note.title).getByRole("button", { name: "Edit" }).click();
    const form = page.getByRole("form", { name: "Edit note" });
    await expect(form.getByLabel("Title")).toBeFocused();
    await form.getByLabel("Content").fill("Changed, then cancelled");
    await form.getByRole("button", { name: "Cancel" }).click();
    await expect(form).toHaveCount(0);
    await expect(noteCard(page, note.title)).toContainText("First point");

    await noteCard(page, note.title).getByRole("button", { name: "Edit" }).click();
    const newTitle = `${note.title} (final)`;
    await form.getByLabel("Title").fill(newTitle);
    await form.getByRole("button", { name: "Medium priority" }).click();
    await form.getByRole("button", { name: "Save" }).click();

    await expect(page.getByText("Note saved.")).toBeVisible();
    await expect(noteCard(page, newTitle)).toContainText("Medium");
    await expect(noteCard(page, newTitle).getByRole("button", { name: "Edit" })).toBeFocused();
    await page.reload();
    await expect(noteCard(page, newTitle)).toContainText("Medium");
  });

  test("Escape in the form cancels the edit", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Escape edit"), "low");
    await page.goto("/");

    await noteCard(page, note.title).getByRole("button", { name: "Edit" }).click();
    await page.getByRole("form", { name: "Edit note" }).getByLabel("Title").press("Escape");

    await expect(page.getByRole("form", { name: "Edit note" })).toHaveCount(0);
    await expect(noteCard(page, note.title)).toBeVisible();
  });

  test("a concurrent edit is detected and can be overwritten knowingly", async ({ page }) => {
    const { request } = page;
    const note = await createNoteViaApi(request, uniqueTitle("Shared shopping list"), "medium");
    await page.goto("/");
    await noteCard(page, note.title).getByRole("button", { name: "Edit" }).click();
    const form = page.getByRole("form", { name: "Edit note" });
    await expect(form.getByLabel("Title")).toHaveValue(note.title);

    // Someone else saves first, from another tab or device.
    const theirs = `${note.title} — theirs`;
    const other = await request.put(`/api/notes/${note.id}`, { data: { title: theirs, priority: "high", version: 1 } });
    expect(other.status()).toBe(200);

    const mine = `${note.title} — mine`;
    await form.getByLabel("Title").fill(mine);
    await form.getByRole("button", { name: "Save" }).click();

    const banner = form.getByRole("alert").filter({ hasText: "changed somewhere else" });
    await expect(banner).toBeVisible();
    await expect(form.getByRole("button", { name: "Save", exact: true })).toBeDisabled();
    await banner.getByText("Show the other version").click();
    await expect(banner).toContainText(theirs);

    await banner.getByRole("button", { name: "Save mine anyway" }).click();
    await expect(page.getByText("Note saved.")).toBeVisible();
    await expect(noteCard(page, mine)).toBeVisible();
  });

  test("a concurrent edit can also be given up in favour of the other version", async ({ page }) => {
    const { request } = page;
    const note = await createNoteViaApi(request, uniqueTitle("Their list"), "low");
    await page.goto("/");
    await noteCard(page, note.title).getByRole("button", { name: "Edit" }).click();
    const form = page.getByRole("form", { name: "Edit note" });
    await expect(form.getByLabel("Title")).toHaveValue(note.title);

    const theirs = `${note.title} — theirs`;
    expect((await request.put(`/api/notes/${note.id}`, { data: { title: theirs, priority: "high", version: 1 } })).status()).toBe(200);
    await form.getByLabel("Title").fill(`${note.title} — mine`);
    await form.getByRole("button", { name: "Save" }).click();

    await form.getByRole("button", { name: "Discard mine, load theirs" }).click();
    await expect(form.getByLabel("Title")).toHaveValue(theirs);
    await form.getByRole("button", { name: "Save" }).click();
    await expect(noteCard(page, theirs)).toContainText("High");
  });

  test("leaving the wall with unsaved edits asks first", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Unsaved"), "low");
    // Something in the archive, so the wall links to it.
    const old = await createNoteViaApi(page.request, uniqueTitle("Archived one"), "low");
    expect((await page.request.post(`/api/notes/${old.id}/archive`)).status()).toBe(200);
    await page.goto("/");
    await noteCard(page, note.title).getByRole("button", { name: "Edit" }).click();
    await page.getByRole("form", { name: "Edit note" }).getByLabel("Title").fill("changed but not saved");

    page.once("dialog", (dialog) => void dialog.dismiss());
    await archiveLink(page).click();
    await expect(page).toHaveURL("/");
    await expect(page.getByRole("form", { name: "Edit note" }).getByLabel("Title")).toHaveValue("changed but not saved");

    page.once("dialog", (dialog) => void dialog.accept());
    await archiveLink(page).click();
    await expect(page).toHaveURL("/archive");
  });

  test("a note deleted elsewhere while being edited says so and leaves the wall", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Vanishing"), "low");
    await page.goto("/");
    await noteCard(page, note.title).getByRole("button", { name: "Edit" }).click();
    expect((await page.request.delete(`/api/notes/${note.id}`)).status()).toBe(204);

    await page.getByRole("form", { name: "Edit note" }).getByRole("button", { name: "Save" }).click();

    await expect(page.getByRole("alert").filter({ hasText: "no longer exists" })).toBeVisible();
    await expect(noteCard(page, note.title)).toHaveCount(0);
  });
});

test.describe("reading on the wall", () => {
  test("a long note is clipped, and Read more opens it in place", async ({ page }) => {
    const lines = Array.from({ length: 14 }, (_, i) => `Line ${i + 1}`).join("\n");
    const note = await createNoteViaApi(page.request, uniqueTitle("Long read"), "medium", lines);
    const short = await createNoteViaApi(page.request, uniqueTitle("Short read"), "medium", "Just one line");
    await page.goto("/");

    await expect(noteCard(page, short.title).getByRole("button", { name: "Read more" })).toHaveCount(0);
    const card = noteCard(page, note.title);
    const more = card.getByRole("button", { name: "Read more" });
    await expect(more).toHaveAttribute("aria-expanded", "false");
    const body = card.locator("p", { hasText: "Line 14" });
    const clipped = () => body.evaluate((element) => element.scrollHeight > element.clientHeight);
    expect(await clipped()).toBe(true);

    await more.click();
    const less = card.getByRole("button", { name: "Show less" });
    await expect(less).toHaveAttribute("aria-expanded", "true");
    expect(await clipped()).toBe(false);
    await less.click();
    await expect(card.getByRole("button", { name: "Read more" })).toBeVisible();
  });
});

test.describe("filtering and sorting", () => {
  test("priority chips filter the wall, and the filter lives in the URL", async ({ page }) => {
    const { request } = page;
    const high = await createNoteViaApi(request, uniqueTitle("Pay rent"), "high");
    const medium = await createNoteViaApi(request, uniqueTitle("Book dentist"), "medium");
    const low = await createNoteViaApi(request, uniqueTitle("Sort photos"), "low");
    await page.goto("/");

    await priorityChip(page, "High").click();
    await expect(page).toHaveURL("/?priority=high");
    await expect(noteCard(page, high.title)).toBeVisible();
    await expect(noteCard(page, medium.title)).toHaveCount(0);
    await expect(noteCard(page, low.title)).toHaveCount(0);
    // A narrowed wall is for finding, not writing: the composer steps aside.
    await expect(quickNote(page)).toHaveCount(0);

    await priorityChip(page, "Low").click();
    await expect(page).toHaveURL("/?priority=high,low");
    await expect(noteCard(page, low.title)).toBeVisible();
    await expect(noteCard(page, medium.title)).toHaveCount(0);

    await page.reload();
    await expect(priorityChip(page, "High")).toHaveAttribute("aria-pressed", "true");
    await expect(priorityChip(page, "Low")).toHaveAttribute("aria-pressed", "true");
    await expect(noteCard(page, medium.title)).toHaveCount(0);

    // Unpressing every chip shows all priorities again.
    await priorityChip(page, "High").click();
    await priorityChip(page, "Low").click();
    await expect(page).toHaveURL("/");
    await expect(noteCard(page, medium.title)).toBeVisible();
  });

  test("an empty filter result offers to show all priorities", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Only low"), "low");
    await page.goto("/?priority=high");

    await expect(page.getByText("No notes with this priority.")).toBeVisible();
    await page.getByRole("button", { name: "Show all priorities" }).click();
    await expect(page).toHaveURL("/");
    await expect(noteCard(page, note.title)).toBeVisible();
  });

  test("the wall sorts by newest, oldest or priority, and the sort lives in the URL", async ({ page }) => {
    const { request } = page;
    const first = await createNoteViaApi(request, uniqueTitle("First"), "low");
    const second = await createNoteViaApi(request, uniqueTitle("Second"), "high");
    const third = await createNoteViaApi(request, uniqueTitle("Third"), "medium");
    // One column, so document order is the list order.
    await page.setViewportSize({ width: 380, height: 900 });
    await page.goto("/");

    await expect(sortButton(page, "Newest")).toHaveAttribute("aria-pressed", "true");
    await expect.poll(() => sheetTitles(page)).toEqual([third.title, second.title, first.title]);

    await sortButton(page, "Oldest").click();
    await expect(page).toHaveURL("/?sort=oldest");
    await expect.poll(() => sheetTitles(page)).toEqual([first.title, second.title, third.title]);

    await sortButton(page, "Priority").click();
    await expect(page).toHaveURL("/?sort=priority");
    await expect.poll(() => sheetTitles(page)).toEqual([second.title, third.title, first.title]);

    await page.reload();
    await expect(sortButton(page, "Priority")).toHaveAttribute("aria-pressed", "true");
    await sortButton(page, "Newest").click();
    await expect(page).toHaveURL("/");
  });
});

test.describe("addresses", () => {
  test("old note addresses land on the wall", async ({ page }) => {
    const note = await createNoteViaApi(page.request, uniqueTitle("Bookmarked"), "low");
    for (const path of [`/notes/${note.id}`, `/notes/${note.id}/edit`, "/notes/new"]) {
      await page.goto(path);
      await expect(page).toHaveURL("/");
      await expect(noteCard(page, note.title)).toBeVisible();
    }
  });

  test("an unknown page offers a way back", async ({ page }) => {
    await page.goto("/no/such/page");
    await expect(page.getByRole("heading", { name: "Page not found" })).toBeVisible();
    await page.getByRole("link", { name: "Go to your notes" }).click();
    await expect(page.getByRole("heading", { name: "Notes", exact: true })).toBeAttached();
    await expect(quickNote(page)).toBeVisible();
  });
});
