import { expect, test } from "@playwright/test";
import { PASSWORD, logInViaForm, registerViaApi, uniqueUsername } from "./helpers/auth";
import { createNoteViaApi, noteCard, uniqueTitle } from "./helpers/notes";

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
    const note = await createNoteViaApi(page.request, uniqueTitle("Deep link"), "low");
    await page.context().clearCookies();

    await page.goto(`/notes/${note.id}`);
    await expect(page).toHaveURL(`/login?redirect=/notes/${note.id}`);

    await logInViaForm(page, { ...account, password: "not the password" });
    await expect(page.getByRole("alert")).toHaveText("Wrong username or password.");
    await expect(page.getByLabel("Password", { exact: true })).toHaveValue("");

    await logInViaForm(page, account);
    await expect(page).toHaveURL(`/notes/${note.id}`);
    await expect(page.getByRole("heading", { name: note.title })).toBeVisible();
  });

  test("a logged-in user is sent past the login page", async ({ page }) => {
    await registerViaApi(page.request);
    await page.goto("/login");
    await expect(page).toHaveURL("/");
  });

  test("an ended session sends the user to log in", async ({ page }) => {
    await registerViaApi(page.request);
    await page.goto("/notes/new");
    await expect(page.getByRole("heading", { name: "New note" })).toBeVisible();

    await page.context().clearCookies(); // as if the session expired meanwhile
    await page.getByLabel("Title").fill("Written after the session ended");
    page.once("dialog", (dialog) => void dialog.accept()); // the unsaved-changes prompt
    await page.getByRole("button", { name: "Create note" }).click();

    await expect(page).toHaveURL("/login?redirect=/notes/new");
    await expect(page.getByText("Your session has ended.")).toBeVisible();
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
    await bobPage.goto(`/notes/${secret.id}`);
    await expect(bobPage.getByRole("alert")).toContainText("This note does not exist");

    await alice.close();
    await bob.close();
  });
});
