import { describe, expect, it } from "vitest";
import router, { accessRedirect } from "../index";

const to = (path: string) => router.resolve(path);

describe("accessRedirect", () => {
  it("sends a logged-out visitor to login, remembering where they were going", () => {
    expect(accessRedirect(to("/"), false)).toEqual({ name: "login" });
    expect(accessRedirect(to("/archive?priority=high"), false)).toEqual({ name: "login", query: { redirect: "/archive?priority=high" } });
    expect(accessRedirect(to("/notes/7"), false)).toEqual({ name: "login", query: { redirect: "/notes/7" } });
  });

  it("lets a logged-in user through to the notes", () => {
    for (const path of ["/", "/archive", "/notes/new", "/notes/7"]) {
      expect(accessRedirect(to(path), true)).toBeUndefined();
    }
  });

  it("keeps a logged-in user away from login and register", () => {
    expect(accessRedirect(to("/login"), true)).toEqual({ name: "notes" });
    expect(accessRedirect(to("/register"), true)).toEqual({ name: "notes" });
    expect(accessRedirect(to("/login"), false)).toBeUndefined();
  });

  it("shows the not-found page to anyone", () => {
    expect(accessRedirect(to("/no/such/page"), false)).toBeUndefined();
  });
});
