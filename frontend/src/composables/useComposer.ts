import { computed, readonly, ref } from "vue";
import type { NoteInput } from "@/domain/note";

const closed = ref(false);
const shown = ref(false);
const inView = ref(false);
const revealPending = ref(false);
const draft = ref<NoteInput>({ title: "", content: "", priority: "medium" });
/** Whose draft it is. A draft never carries over to another account in the same tab. */
let owner: number | null = null;

/**
 * The quick-note composer on the wall, shared with the header's New note button. Module-level
 * state, so the draft survives a visit to the archive and the header knows whether the composer
 * is on screen (the button shows only when it is not).
 */
export function useComposer() {
  /** The header's New note: open the composer again; the wall scrolls to it and focuses it. */
  function reveal(): void {
    closed.value = false;
    revealPending.value = true;
  }

  /** The wall brought the composer into view. */
  function revealHandled(): void {
    revealPending.value = false;
  }

  function close(): void {
    closed.value = true;
  }

  function resetDraft(): void {
    draft.value = { title: "", content: "", priority: "medium" };
  }

  /**
   * The logged-in user changed. Another account starts with an empty, open composer; the same user
   * coming back after an expired session finds the draft they were writing.
   */
  function belongTo(userId: number | null): void {
    if (userId === null || userId === owner) return;
    if (owner !== null) {
      resetDraft();
      closed.value = false;
    }
    owner = userId;
  }

  /** Reported by the wall: whether it shows the composer now, and whether it is in view. */
  function report(state: { shown?: boolean; inView?: boolean }): void {
    if (state.shown !== undefined) shown.value = state.shown;
    if (state.inView !== undefined) inView.value = state.inView;
  }

  const hasDraft = computed(() => draft.value.title.trim() !== "" || draft.value.content.trim() !== "");
  const newNoteVisible = computed(() => !(shown.value && inView.value));

  return {
    closed: readonly(closed),
    draft,
    hasDraft,
    newNoteVisible,
    revealPending: readonly(revealPending),
    reveal,
    revealHandled,
    close,
    resetDraft,
    belongTo,
    report,
  };
}

/** Test hook: back to an open composer with an empty draft. */
export function resetComposerForTests(): void {
  closed.value = false;
  shown.value = false;
  inView.value = false;
  revealPending.value = false;
  draft.value = { title: "", content: "", priority: "medium" };
  owner = null;
}
