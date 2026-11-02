import { afterEach, describe, expect, it, vi } from "vitest";
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
};

function mockFetch(status: number, body: unknown) {
  const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status }));
  vi.stubGlobal("fetch", fetchMock);
  return fetchMock;
}

afterEach(() => vi.unstubAllGlobals());

describe("notesApi", () => {
  it("lists with the priority filter as one comma-separated parameter", async () => {
    const fetchMock = mockFetch(200, { data: [note] });

    await expect(notesApi.list(["high", "low"])).resolves.toEqual([note]);
    expect(fetchMock.mock.calls[0]?.[0]).toBe("/api/notes?priority=high%2Clow");
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
