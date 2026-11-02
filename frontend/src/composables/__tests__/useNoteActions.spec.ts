import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError, notesApi } from "@/api/notes";
import { useFlash } from "@/composables/useFlash";
import { useNoteActions } from "@/composables/useNoteActions";
import type { Note } from "@/domain/note";

const note: Note = {
  id: 7,
  title: "Groceries",
  content: "",
  priority: "low",
  version: 1,
  createdAt: "2026-11-02T10:00:00+00:00",
  updatedAt: "2026-11-02T10:00:00+00:00",
  archivedAt: null,
};

function setup() {
  const handlers = { changed: vi.fn(), deleted: vi.fn(), gone: vi.fn() };
  return { handlers, actions: useNoteActions(handlers) };
}

describe("useNoteActions", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    useFlash().dismiss();
  });

  it("archives, reports the stored note and confirms with a flash", async () => {
    const archived = { ...note, archivedAt: "2026-11-02T11:00:00+00:00", version: 2 };
    vi.spyOn(notesApi, "archive").mockResolvedValue(archived);
    const { handlers, actions } = setup();

    const pending = actions.archive(note);
    expect(actions.busyId.value).toBe(7);
    await pending;

    expect(handlers.changed).toHaveBeenCalledWith(archived);
    expect(actions.busyId.value).toBeNull();
    expect(useFlash().message.value).toBe("Note archived. Find it under Archived.");
  });

  it("treats a 404 as a note deleted elsewhere", async () => {
    vi.spyOn(notesApi, "restore").mockRejectedValue(new ApiError("Not found.", 404));
    const { handlers, actions } = setup();

    await actions.restore(note);

    expect(handlers.gone).toHaveBeenCalledWith(note);
    expect(handlers.changed).not.toHaveBeenCalled();
    expect(actions.error.value).toContain("no longer exists");
  });

  it("stays quiet on a 401, which sends the user to login elsewhere", async () => {
    vi.spyOn(notesApi, "archive").mockRejectedValue(new ApiError("Log in.", 401));
    const { actions } = setup();

    await actions.archive(note);

    expect(actions.error.value).toBeNull();
  });

  it("deletes only after confirmation, and cannot be cancelled while running", async () => {
    let finish: () => void = () => {};
    const remove = vi.spyOn(notesApi, "delete").mockReturnValue(new Promise<void>((resolve) => (finish = resolve)));
    const { handlers, actions } = setup();

    actions.requestDelete(note);
    expect(remove).not.toHaveBeenCalled();
    expect(actions.pendingDelete.value).toEqual(note);

    const pending = actions.confirmDelete();
    actions.cancelDelete(); // ignored: the request is already on its way
    expect(actions.pendingDelete.value).toEqual(note);
    finish();
    await pending;

    expect(remove).toHaveBeenCalledWith(7);
    expect(handlers.deleted).toHaveBeenCalledWith(note);
    expect(actions.pendingDelete.value).toBeNull();
    expect(useFlash().message.value).toBe("Note deleted.");
  });

  it("closes the confirmation and reports a failed delete", async () => {
    vi.spyOn(notesApi, "delete").mockRejectedValue(new ApiError("Server unavailable.", 503));
    const { handlers, actions } = setup();

    actions.requestDelete(note);
    await actions.confirmDelete();

    expect(handlers.deleted).not.toHaveBeenCalled();
    expect(actions.pendingDelete.value).toBeNull();
    expect(actions.error.value).toBe("Server unavailable.");
  });
});
