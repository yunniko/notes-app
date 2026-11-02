import { ref } from "vue";
import { ApiError, notesApi } from "@/api/notes";
import { useFlash } from "@/composables/useFlash";
import type { Note } from "@/domain/note";

export interface NoteActionHandlers {
  /** Archive or restore succeeded; `updated` is the note as now stored. */
  changed(updated: Note): void;
  /** The note was deleted here. */
  deleted(note: Note): void;
  /** The note turned out to be gone already (deleted in another tab). */
  gone(note: Note): void;
}

/**
 * Archive, restore and delete with the same feedback wherever a note is shown: one action at
 * a time, a flash on success, a plain message on failure, and delete behind a confirmation.
 */
export function useNoteActions(handlers: NoteActionHandlers) {
  const flash = useFlash();
  const busyId = ref<number | null>(null);
  const error = ref<string | null>(null);
  /** The note whose delete awaits confirmation; drives the ConfirmDialog. */
  const pendingDelete = ref<Note | null>(null);

  async function run<T>(note: Note, action: () => Promise<T>, done: string): Promise<T | undefined> {
    busyId.value = note.id;
    error.value = null;
    try {
      const result = await action();
      flash.show(done);
      return result;
    } catch (e) {
      if (e instanceof ApiError && e.status === 404) {
        handlers.gone(note);
        error.value = `“${note.title}” no longer exists. It was probably deleted somewhere else.`;
      } else if (!(e instanceof ApiError && e.status === 401)) {
        // A 401 is handled globally (back to login), so it needs no message here.
        error.value = e instanceof ApiError ? e.message : "Something went wrong. Please try again.";
      }
      return undefined;
    } finally {
      busyId.value = null;
    }
  }

  async function archive(note: Note): Promise<void> {
    const updated = await run(note, () => notesApi.archive(note.id), "Note archived. Find it under Archived notes, below the wall.");
    if (updated) handlers.changed(updated);
  }

  async function restore(note: Note): Promise<void> {
    const updated = await run(note, () => notesApi.restore(note.id), "Note restored.");
    if (updated) handlers.changed(updated);
  }

  function requestDelete(note: Note): void {
    pendingDelete.value = note;
  }

  function cancelDelete(): void {
    if (busyId.value === null) pendingDelete.value = null;
  }

  async function confirmDelete(): Promise<void> {
    const note = pendingDelete.value;
    if (!note) return;
    const ok = await run(
      note,
      async () => {
        await notesApi.delete(note.id);
        return true;
      },
      "Note deleted."
    );
    pendingDelete.value = null;
    if (ok) handlers.deleted(note);
  }

  return { busyId, error, pendingDelete, archive, restore, requestDelete, cancelDelete, confirmDelete };
}
