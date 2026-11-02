<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, useTemplateRef, watch } from "vue";
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from "vue-router";
import { ApiError, notesApi, type NoteCounts } from "@/api/notes";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import NoteSheet from "@/components/NoteSheet.vue";
import PriorityFilter from "@/components/PriorityFilter.vue";
import QuickNote from "@/components/QuickNote.vue";
import SortControl from "@/components/SortControl.vue";
import { useColumnCount } from "@/composables/useColumnCount";
import { useComposer } from "@/composables/useComposer";
import { useFlash } from "@/composables/useFlash";
import { useNoteActions } from "@/composables/useNoteActions";
import type { Note } from "@/domain/note";
import { parsePriorityFilter, serializePriorityFilter, type Priority } from "@/domain/priority";
import { emptyListMessage, parseSearch, serializeSearch } from "@/domain/search";
import { parseSort, serializeSort, type NoteSort } from "@/domain/sort";
import { dealColumns } from "@/domain/wall";

/** The wall of active notes at "/", the archive at "/archive"; one view for both. */
const props = defineProps<{ archived: boolean }>();
const route = useRoute();
const router = useRouter();
const flash = useFlash();
const composer = useComposer();

type State = { status: "loading" } | { status: "error"; message: string } | { status: "ready"; notes: Note[] };
const state = ref<State>({ status: "loading" });
const counts = ref<NoteCounts | null>(null);
const listRoute = computed(() => (props.archived ? "archive" : "notes"));

// --- What is shown: filter, search and sort live in the URL (the search box is in the header) ----

const filter = computed<Priority[]>({
  get: () => parsePriorityFilter(route.query.priority),
  set: (priorities) => router.replace({ query: { ...route.query, priority: serializePriorityFilter(priorities) } }),
});
const search = computed<string>({
  get: () => parseSearch(route.query.q),
  set: (text) => router.replace({ query: { ...route.query, q: serializeSearch(text) } }),
});
const sort = computed<NoteSort>({
  get: () => parseSort(route.query.sort),
  set: (order) => router.replace({ query: { ...route.query, sort: serializeSort(order) } }),
});
const narrowed = computed(() => search.value !== "" || filter.value.length > 0);
const emptyMessage = computed(() =>
  emptyListMessage({ archived: props.archived, search: search.value, filtered: filter.value.length > 0 })
);
/** The other list keeps the same filter, search and sort. */
const otherListLink = computed(() => ({ name: props.archived ? "notes" : "archive", query: route.query }));

// --- Loading ------------------------------------------------------------------------------------

let controller: AbortController | undefined;
let loadedList: string | undefined;

async function load(): Promise<void> {
  controller?.abort(); // a newer filter wins over a slower, older response
  controller = new AbortController();
  const { signal } = controller;
  // Keep the cards while only filter, search or sort change; switching wall and archive starts over.
  if (state.value.status !== "ready" || loadedList !== listRoute.value) state.value = { status: "loading" };
  try {
    const list = await notesApi.list(
      { priorities: filter.value, archived: props.archived, search: search.value, sort: sort.value },
      signal
    );
    state.value = { status: "ready", notes: list.notes };
    counts.value = list.counts;
    loadedList = listRoute.value;
  } catch (error) {
    if (signal.aborted) return;
    state.value = { status: "error", message: error instanceof ApiError ? error.message : "Something went wrong." };
  }
}

// Keyed on what the list shows, so unrelated route changes (e.g. leaving the page) do not refetch.
const loadKey = computed(() =>
  route.name === listRoute.value ? [listRoute.value, serializePriorityFilter(filter.value), search.value, sort.value].join("|") : null
);
watch(loadKey, (key) => key !== null && load(), { immediate: true });

const notes = computed(() => (state.value.status === "ready" ? state.value.notes : []));

function removeFromList(id: number): void {
  if (state.value.status === "ready") state.value.notes = state.value.notes.filter((n) => n.id !== id);
}

// --- The wall: masonry columns, with the composer first -----------------------------------------

const wall = useTemplateRef<HTMLElement>("wall");
const columnCount = useColumnCount(wall);
const showComposer = computed(() => !props.archived && !narrowed.value && !composer.closed.value);
const columns = computed(() => dealColumns(notes.value, columnCount.value, showComposer.value ? 1 : 0));

// A function ref: the composer is rendered inside the column v-for, where a named template ref
// would collect an array instead of the one instance.
const quickNote = shallowRef<InstanceType<typeof QuickNote> | null>(null);
function setQuickNote(instance: unknown): void {
  quickNote.value = (instance as InstanceType<typeof QuickNote> | null) ?? null;
}
const quickNoteElement = computed<HTMLElement | null>(() => (quickNote.value?.$el as HTMLElement | undefined) ?? null);

// The header's New note shows only while the composer is off screen.
let visibility: IntersectionObserver | undefined;
watch(
  quickNoteElement,
  (element) => {
    visibility?.disconnect();
    composer.report({ shown: element !== null, inView: false });
    if (!element || typeof IntersectionObserver === "undefined") return;
    visibility = new IntersectionObserver(([entry]) => composer.report({ inView: entry?.isIntersecting ?? false }), {
      rootMargin: "-72px 0px 0px 0px",
    });
    visibility.observe(element);
  },
  { immediate: true }
);
onBeforeUnmount(() => {
  visibility?.disconnect();
  composer.report({ shown: false, inView: false });
});

// New note in the header asked for the composer: bring it into view and focus it.
watch(
  [composer.revealPending, quickNoteElement],
  async ([pending, element]) => {
    if (!pending || !element) return;
    composer.revealHandled();
    await nextTick();
    element.scrollIntoView({ behavior: "smooth", block: "center" });
    quickNote.value?.focus({ preventScroll: true });
  },
  { immediate: true }
);

function onCreated(): void {
  flash.show("Note created.");
  void load();
}

// --- Reading and editing a note in place --------------------------------------------------------

const expandedId = ref<number | null>(null);
const editingId = ref<number | null>(null);
const editingDirty = ref(false);
const actionError = ref<string | null>(null);

function confirmDiscard(): boolean {
  return !editingDirty.value || window.confirm("You have unsaved changes. Leave without saving?");
}

function stopEditing(): void {
  editingId.value = null;
  editingDirty.value = false;
}

async function focusEditButton(id: number): Promise<void> {
  await nextTick();
  wall.value?.querySelector<HTMLButtonElement>(`[data-note-id="${id}"] .note__edit`)?.focus();
}

function startEditing(note: Note): void {
  if (editingId.value === note.id || !confirmDiscard()) return;
  actionError.value = null;
  editingId.value = note.id;
  editingDirty.value = false;
}

function cancelEditing(note: Note): void {
  stopEditing();
  void focusEditButton(note.id);
}

async function onSaved(note: Note): Promise<void> {
  stopEditing();
  flash.show("Note saved.");
  await load();
  void focusEditButton(note.id);
}

function onGone(note: Note): void {
  stopEditing();
  removeFromList(note.id);
  actionError.value = `“${note.title}” no longer exists. It was probably deleted somewhere else.`;
  void load();
}

function toggle(note: Note): void {
  expandedId.value = expandedId.value === note.id ? null : note.id;
}

// Unsaved edits: ask before leaving the page or changing what the wall shows, and before closing the tab.
const guard = (): boolean => {
  if (!confirmDiscard()) return false;
  stopEditing();
  return true;
};
onBeforeRouteLeave(guard);
onBeforeRouteUpdate(guard);
const onBeforeUnload = (event: BeforeUnloadEvent) => {
  if (editingDirty.value || composer.hasDraft.value) event.preventDefault();
};
onMounted(() => window.addEventListener("beforeunload", onBeforeUnload));
onBeforeUnmount(() => window.removeEventListener("beforeunload", onBeforeUnload));

// --- Archive, restore, delete -------------------------------------------------------------------

// Archiving or restoring moves the note to the other list; either way it leaves this one.
const actions = useNoteActions({
  changed: (note) => {
    removeFromList(note.id);
    void load();
  },
  deleted: (note) => {
    removeFromList(note.id);
    void load();
  },
  gone: (note) => {
    removeFromList(note.id);
    void load();
  },
});
const { busyId, error: notesActionError, pendingDelete } = actions;
const shownError = computed(() => notesActionError.value ?? actionError.value);
</script>

<template>
  <section class="wall-view" :aria-labelledby="archived ? 'archive-heading' : 'wall-heading'">
    <div v-if="archived" class="archive-head">
      <RouterLink class="quiet archive-head__back" :to="otherListLink">← Back to notes</RouterLink>
      <h1 id="archive-heading" class="display">Archive</h1>
      <p class="lead">Notes kept out of your wall. Restore brings one back.</p>
    </div>

    <div class="controls">
      <h1 v-if="!archived" id="wall-heading" class="sr-only">Notes</h1>
      <PriorityFilter v-model="filter" />
      <SortControl v-model="sort" class="controls__sort" />
    </div>

    <p v-if="shownError" class="board-error" role="alert">{{ shownError }}</p>

    <p v-if="state.status === 'loading'" class="board-box board-box__message" role="status">Loading notes…</p>

    <div v-else-if="state.status === 'error'" class="board-box" role="alert">
      <p class="board-box__message">{{ state.message }}</p>
      <div class="board-box__actions">
        <button type="button" class="chip" @click="load">Try again</button>
      </div>
    </div>

    <template v-else>
      <p class="sr-only" role="status">{{ notes.length }} {{ notes.length === 1 ? "note" : "notes" }} shown</p>

      <div v-if="notes.length === 0 && (narrowed || archived || !showComposer)" class="board-box">
        <template v-if="emptyMessage">
          <p class="board-box__message">{{ emptyMessage }}</p>
          <div class="board-box__actions">
            <button v-if="search" type="button" class="chip" @click="search = ''">Clear search</button>
            <button v-if="filter.length > 0" type="button" class="chip" @click="filter = []">Show all priorities</button>
          </div>
        </template>
        <p v-else-if="archived" class="board-box__message">
          Nothing archived. Archive a note to keep it off your wall without deleting it.
        </p>
        <p v-else class="board-box__message">No notes yet. Write down the first thing on your mind.</p>
      </div>
      <p v-else-if="notes.length === 0" class="lead wall-hint">No notes yet. Write down the first thing on your mind.</p>

      <div ref="wall" class="wall" :aria-label="archived ? 'Archived notes' : 'Notes'" role="region">
        <div v-for="(column, index) in columns" :key="index" class="wall__column">
          <QuickNote v-if="showComposer && index === 0" :ref="setQuickNote" @created="onCreated" @close="composer.close" />
          <NoteSheet
            v-for="note in column"
            :key="note.id"
            :note="note"
            :expanded="expandedId === note.id"
            :editing="editingId === note.id"
            :busy="busyId === note.id"
            @toggle="toggle(note)"
            @edit="startEditing(note)"
            @cancel="cancelEditing(note)"
            @saved="onSaved"
            @gone="onGone(note)"
            @dirty="editingDirty = $event"
            @archive="actions.archive(note)"
            @restore="actions.restore(note)"
            @delete="actions.requestDelete(note)"
          />
        </div>
      </div>
    </template>

    <!-- The archive link shows only when the archive holds something. -->
    <template v-if="!archived && counts !== null && counts.archived > 0">
      <div class="rule wall-foot"></div>
      <p class="wall-foot__link">
        <RouterLink class="quiet" :to="otherListLink">Archived notes ({{ counts.archived }})</RouterLink>
      </p>
    </template>

    <ConfirmDialog
      :open="pendingDelete !== null"
      title="Delete this note?"
      confirm-label="Delete permanently"
      :busy="busyId !== null"
      @confirm="actions.confirmDelete"
      @cancel="actions.cancelDelete"
    >
      <p>
        “{{ pendingDelete?.title }}” will be deleted for good. This cannot be undone.
        <template v-if="!archived">To keep it out of the way instead, archive it.</template>
      </p>
    </ConfirmDialog>
  </section>
</template>

<style scoped>
.wall-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.archive-head {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
}
.archive-head__back {
  padding-left: 0;
}
.archive-head .lead {
  margin-top: 2px;
}
.controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}
.controls__sort {
  margin-left: auto;
}
.board-error {
  margin: 0;
  padding: 12px 16px;
  border: 1px solid var(--error-on-board);
  border-radius: 2px;
  color: var(--error-on-board);
  background: rgb(138 43 31 / 0.15);
}
.wall-hint {
  margin: 0;
}
.wall {
  display: flex;
  gap: 24px;
  align-items: flex-start;
}
.wall__column {
  flex: 1 1 0;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.wall-foot {
  margin-top: 16px;
}
.wall-foot__link {
  margin: 0;
  text-align: center;
}
</style>
