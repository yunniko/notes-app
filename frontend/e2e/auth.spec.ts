import { expect, test } from "@playwright/test";
import { PASSWORD, logInViaForm, registerViaApi, uniqueUsername } from "./helpers/auth";
import { createNoteViaApi, noteCard, priorityChip, quickNote, uniqueTitle } from "./helpers/notes";

test.describe("accounts", () => {
  test("a visitor registers, lands on an empty list, and logs out", async ({ page }) => {
    const username = uniqueUsername("new");
    await page.goto("/");
    await expect(page).toHaveURL("/login");

    await page.getByRole("link", { name: "Create an account" }).click();
    await expect(page.getByRole("heading", { name: "Create account" })).toBeVisible();
    await page.getByLabel("Username").fill(username);
    await page.getByLabel("Password", { exact: true }).fill("short");
    await page.getByRole("button", { name: "Create account" }).click();
    await expect(page.getByText("Password must be at least 8 characters.")).toBeVisible();

    await page.getByLabel("Password", { exact: true }).fill(PASSWORD);
    await page.getByRole("button", { name: "Create account" }).click();

    await expect(page).toHaveURL("/");
    await expect(page.getByText("No notes yet.")).toBeVisible();
    await expect(page.getByRole("banner").getByRole("button", { name: `Account: ${username}` })).toBeVisible();

    await page.getByRole("button", { name: /^Account: / }).click();
    await page.getByRole("button", { name: "Log out" }).click();
    await expect(page).toHaveURL("/login");
    await expect(page.getByText("You are logged out.")).toBeVisible();
    await page.goto("/");
    await expect(page).toHaveURL("/login");
  });

  test("a taken username is reported on the field", async ({ page }) => {
    const account = await registerViaApi(page.context().request);
    await page.context().clearCookies();
    await page.goto("/register");

    await page.getByLabel("Username").fill(account.username.toUpperCase()); // usernames are case-insensitive
    await page.getByLabel("Password", { exact: true }).fill(PASSWORD);
    await page.getByRole("button", { name: "Create account" }).click();

    await expect(page.getByText("This username is taken.")).toBeVisible();
    await expect(page).toHaveURL("/register");
  });

  test("a wrong password is refused, the right one goes back where the visitor was heading", async ({ page }) => {
    const account = await registerViaApi(page.request);
    const note = await createNoteViaApi(page.request, uniqueTitle("Deep link"), "high");
    await page.context().clearCookies();

    await page.goto("/?priority=high");
    await expect(page).toHaveURL(/\/login\?/);
    expect(new URL(page.url()).searchParams.get("redirect")).toBe("/?priority=high");

    await logInViaForm(page, { ...account, password: "not the password" });
    await expect(page.getByRole("alert")).toHaveText("Wrong username or password.");
    await expect(page.getByLabel("Password", { exact: true })).toHaveValue("");

    await logInViaForm(page, account);
    await expect(page).toHaveURL("/?priority=high");
    await expect(priorityChip(page, "High")).toHaveAttribute("aria-pressed", "true");
    await expect(noteCard(page, note.title)).toBeVisible();
  });

  test("a logged-in user is sent past the login page", async ({ page }) => {
    await registerViaApi(page.request);
    await page.goto("/login");
    await expect(page).toHaveURL("/");
  });

  test("an ended session sends the user to log in, and their draft waits for them", async ({ page }) => {
    const account = await registerViaApi(page.request);
    await page.goto("/");
    const composer = quickNote(page);
    await expect(composer).toBeVisible();

    await page.context().clearCookies(); // as if the session expired meanwhile
    await composer.getByLabel("Title").fill("Written after the session ended");
    await composer.getByRole("button", { name: "Add note" }).click();

    await expect(page).toHaveURL("/login");
    await expect(page.getByText("Your session has ended.")).toBeVisible();
    await logInViaForm(page, account);
    await expect(quickNote(page).getByLabel("Title")).toHaveValue("Written after the session ended");
  });

  test("a draft is never handed to the next account in the same tab", async ({ page }) => {
    await registerViaApi(page.request);
    await page.goto("/");
    await quickNote(page).getByLabel("Title").fill("Not for anyone else");
    // A second account, registered without reloading the page (a reload would wipe the draft anyway).
    const other = await registerViaApi(page.request);
    await page.context().clearCookies();
    await quickNote(page).getByRole("button", { name: "Add note" }).click();
    await expect(page).toHaveURL("/login");

    await logInViaForm(page, other);
    await expect(page).toHaveURL("/");
    await expect(quickNote(page).getByLabel("Title")).toHaveValue("");
  });
});

test.describe("privacy", () => {
  test("one user never sees another user's notes", async ({ browser }) => {
    const alice = await browser.newContext();
    const bob = await browser.newContext();
    const alicePage = await alice.newPage();
    const bobPage = await bob.newPage();
    await registerViaApi(alicePage.request);
    await registerViaApi(bobPage.request);
    const secret = await createNoteViaApi(alicePage.request, uniqueTitle("Alice's secret"), "high");

    await alicePage.goto("/");
    await expect(noteCard(alicePage, secret.title)).toBeVisible();

    await bobPage.goto("/");
    await expect(bobPage.getByText("No notes yet.")).toBeVisible();
    expect((await bobPage.request.get(`/api/notes/${secret.id}`)).status()).toBe(404);

    await alice.close();
    await bob.close();
  });
});
