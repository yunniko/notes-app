import { afterEach, describe, expect, it, vi } from "vitest";
import { setUnauthorizedHandler } from "../http";
import { ApiError, notesApi } from "../notes";
import type { Note } from "@/domain/note";

const note: Note = {
  id: 7,
  title: "Dentist",
  content: "",
  priority: "high",
  version: 2,
  createdAt: "2026-11-02T08:00:00+00:00",
  updatedAt: "2026-11-02T09:00:00+00:00",
  archivedAt: null,
};

function mockFetch(status: number, body: unknown) {
  // A fresh Response per call: a body can be read only once.
  const fetchMock = vi.fn().mockImplementation(() => Promise.resolve(new Response(JSON.stringify(body), { status })));
  vi.stubGlobal("fetch", fetchMock);
  return fetchMock;
}

afterEach(() => {
  vi.unstubAllGlobals();
  setUnauthorizedHandler(undefined);
});

describe("notesApi", () => {
  it("lists with the priority filter as one comma-separated parameter", async () => {
    const fetchMock = mockFetch(200, { data: [note] });

    await expect(notesApi.list({ priorities: ["high", "low"] })).resolves.toEqual([note]);
    expect(fetchMock.mock.calls[0]?.[0]).toBe("/api/notes?priority=high%2Clow");
  });

  it("asks for the archived notes only when told to", async () => {
    const fetchMock = mockFetch(200, { data: [] });

    await notesApi.list();
    await notesApi.list({ archived: true, priorities: ["low"] });

    expect(fetchMock.mock.calls.map((call) => call[0])).toEqual(["/api/notes", "/api/notes?priority=low&archived=true"]);
  });

  it("sends a search as q, encoded, and leaves a blank one out", async () => {
    const fetchMock = mockFetch(200, { data: [] });

    await notesApi.list({ search: "  café & 50% " });
    await notesApi.list({ search: "   " });

    expect(fetchMock.mock.calls.map((call) => call[0])).toEqual(["/api/notes?q=caf%C3%A9+%26+50%25", "/api/notes"]);
  });

  it("archives and restores with a POST to the note's action URL", async () => {
    const fetchMock = mockFetch(200, { data: { ...note, archivedAt: "2026-11-02T10:00:00+00:00" } });

    await expect(notesApi.archive(7)).resolves.toMatchObject({ archivedAt: "2026-11-02T10:00:00+00:00" });
    await notesApi.restore(7);

    expect(fetchMock.mock.calls.map(([url, init]) => [(init as RequestInit).method, url])).toEqual([
      ["POST", "/api/notes/7/archive"],
      ["POST", "/api/notes/7/restore"],
    ]);
  });

  it("resolves a 204 delete without reading a body", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }));
    vi.stubGlobal("fetch", fetchMock);

    await expect(notesApi.delete(7)).resolves.toBeUndefined();
    expect((fetchMock.mock.calls[0] as [string, RequestInit])[1].method).toBe("DELETE");
  });

  it("reports a 401 on a notes request as an ended session", async () => {
    const handler = vi.fn();
    setUnauthorizedHandler(handler);
    mockFetch(401, { status: 401, title: "Authentication required", detail: "Log in to continue." });

    const error = (await notesApi.list().catch((e: unknown) => e)) as ApiError;
    expect(error.status).toBe(401);
    expect(handler).toHaveBeenCalledOnce();
  });

  it("sends the version with an update as JSON", async () => {
    const fetchMock = mockFetch(200, { data: note });

    await notesApi.update(7, { title: "Dentist", content: "", priority: "high" }, 1);

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe("/api/notes/7");
    expect(init.method).toBe("PUT");
    expect(new Headers(init.headers).get("Content-Type")).toBe("application/json");
    expect(JSON.parse(String(init.body))).toEqual({ title: "Dentist", content: "", priority: "high", version: 1 });
  });

  it("turns a 422 problem response into field errors", async () => {
    mockFetch(422, { status: 422, title: "Validation failed", detail: "Invalid.", errors: { title: "Title is required." } });

    const error = await notesApi.create({ title: "", content: "", priority: "low" }).catch((e: unknown) => e);
    expect(error).toBeInstanceOf(ApiError);
    expect((error as ApiError).fieldErrors).toEqual({ title: "Title is required." });
  });

  it("exposes the stored note on a 409 conflict", async () => {
    mockFetch(409, { status: 409, title: "Edit conflict", detail: "Changed.", current: note });

    const error = (await notesApi.update(7, { title: "x", content: "", priority: "low" }, 1).catch((e: unknown) => e)) as ApiError;
    expect(error.isConflict).toBe(true);
    expect(error.current).toEqual(note);
  });

  it("reports a network failure in plain words", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("Failed to fetch")));

    const error = (await notesApi.list().catch((e: unknown) => e)) as ApiError;
    expect(error.status).toBe(0);
    expect(error.message).toMatch(/Cannot reach the server/);
  });

  it("handles a non-JSON error page (e.g. a proxy 502)", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("<html>Bad gateway</html>", { status: 502 })));

    const error = (await notesApi.list().catch((e: unknown) => e)) as ApiError;
    expect(error.status).toBe(502);
    expect(error.message).toMatch(/502/);
  });
});
