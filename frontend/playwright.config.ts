import process from "node:process";
import { defineConfig, devices } from "@playwright/test";

// End-to-end tests drive the real stack (nginx + PHP-FPM + MariaDB), started with
// `docker compose up --build` from the repository root. They create uniquely named notes
// and never delete anything, so they are safe to run against a dev database.
export default defineConfig({
  testDir: "./e2e",
  timeout: 30_000,
  expect: { timeout: 5_000 },
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [["list"], ["html", { open: "never" }]] : "list",
  use: {
    baseURL: process.env.E2E_BASE_URL ?? "http://localhost:8088",
    trace: "retain-on-failure",
  },
  projects: [
    { name: "chromium", use: { ...devices["Desktop Chrome"] } },
    { name: "mobile", use: { ...devices["Pixel 7"] } },
  ],
});
