import { test as base, expect, type APIRequestContext, type Page } from "@playwright/test";

export const PASSWORD = "correct horse battery";

export interface Account {
  username: string;
  password: string;
}

/** A username no other run will produce (max 32 characters), so specs can share one database. */
export function uniqueUsername(label = "u"): string {
  return `${label}${Date.now().toString(36)}${Math.random().toString(36).slice(2, 7)}`.slice(0, 32);
}

/** Registers through the API. With `page.request` the session cookie lands in the page's browser context. */
export async function registerViaApi(request: APIRequestContext, username = uniqueUsername()): Promise<Account> {
  const response = await request.post("/api/auth/register", { data: { username, password: PASSWORD } });
  expect(response.status()).toBe(201);
  return { username, password: PASSWORD };
}

export async function logInViaForm(page: Page, account: Account): Promise<void> {
  await page.getByLabel("Username").fill(account.username);
  await page.getByLabel("Password", { exact: true }).fill(account.password);
  await page.getByRole("button", { name: "Log in" }).click();
}

/**
 * `test` with a fresh account per test, already logged in: the page and `page.request` share its
 * session. Notes created through `page.request` therefore belong to the same user the page shows.
 */
export const test = base.extend<{ account: Account }>({
  account: [
    async ({ page }, use) => {
      await use(await registerViaApi(page.request));
    },
    { auto: true },
  ],
});

export { expect };
