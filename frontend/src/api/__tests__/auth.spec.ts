import { afterEach, describe, expect, it, vi } from "vitest";
import { authApi } from "../auth";
import { setUnauthorizedHandler, type ApiError } from "../http";

const user = { id: 1, username: "alice", createdAt: "2026-11-02T08:00:00+00:00" };

function mockFetch(status: number, body: unknown, headers: Record<string, string> = {}) {
  const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status, headers }));
  vi.stubGlobal("fetch", fetchMock);
  return fetchMock;
}

afterEach(() => {
  vi.unstubAllGlobals();
  setUnauthorizedHandler(undefined);
});

describe("authApi", () => {
  it("posts the credentials as JSON and returns the user", async () => {
    const fetchMock = mockFetch(201, { data: user });

    await expect(authApi.register({ username: "alice", password: "correct horse" })).resolves.toEqual(user);

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect([init.method, url]).toEqual(["POST", "/api/auth/register"]);
    expect(JSON.parse(String(init.body))).toEqual({ username: "alice", password: "correct horse" });
  });

  it("answers null from me() when nobody is logged in", async () => {
    mockFetch(401, { status: 401, title: "Authentication required", detail: "Log in to continue." });

    await expect(authApi.me()).resolves.toBeNull();
  });

  it("keeps a wrong password out of the ended-session handler", async () => {
    const handler = vi.fn();
    setUnauthorizedHandler(handler);
    mockFetch(401, { status: 401, title: "Login failed", detail: "Wrong username or password." });

    const error = (await authApi.login({ username: "alice", password: "nope" }).catch((e: unknown) => e)) as ApiError;
    expect(error.message).toBe("Wrong username or password.");
    expect(handler).not.toHaveBeenCalled();
  });

  it("reads Retry-After from a 429", async () => {
    mockFetch(429, { status: 429, title: "Too many attempts", detail: "Too many failed logins." }, { "Retry-After": "900" });

    const error = (await authApi.login({ username: "alice", password: "nope" }).catch((e: unknown) => e)) as ApiError;
    expect([error.status, error.retryAfterSeconds]).toEqual([429, 900]);
  });

  it("logs out with an empty 204", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status: 204 })));

    await expect(authApi.logout()).resolves.toBeUndefined();
  });
});
