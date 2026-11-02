<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { ApiError, notesApi } from "@/api/notes";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import NoteCard from "@/components/NoteCard.vue";
import PriorityFilter from "@/components/PriorityFilter.vue";
import { useFlash } from "@/composables/useFlash";
import type { Note } from "@/domain/note";
import { parsePriorityFilter, serializePriorityFilter, type Priority } from "@/domain/priority";

/** The active notes at "/", the archived ones at "/archive"; the same page otherwise. */
const props = defineProps<{ archived: boolean }>();
const route = useRoute();
const router = useRouter();
const flash = useFlash();

type State = { status: "loading" } | { status: "error"; message: string } | { status: "ready"; notes: Note[] };
const state = ref<State>({ status: "loading" });
const listRoute = computed(() => (props.archived ? "archive" : "notes"));

/** The filter lives in the URL, so it survives reload and can be bookmarked or shared. */
const filter = computed<Priority[]>({
  get: () => parsePriorityFilter(route.query.priority),
  set: (priorities) => router.replace({ query: { ...route.query, priority: serializePriorityFilter(priorities) } }),
});

let controller: AbortController | undefined;
let loadedKey: string | undefined;

async function load(key: string): Promise<void> {
  controller?.abort(); // a newer filter wins over a slower, older response
  controller = new AbortController();
  const { signal } = controller;
  // Keep the old cards while only the filter changes; switching Active/Archived starts over.
  if (state.value.status !== "ready" || loadedKey?.split("|")[0] !== key.split("|")[0]) state.value = { status: "loading" };
  try {
    const notes = await notesApi.list({ priorities: filter.value, archived: props.archived }, signal);
    state.value = { status: "ready", notes };
    loadedKey = key;
  } catch (error) {
    if (signal.aborted) return;
    state.value = { status: "error", message: error instanceof ApiError ? error.message : "Something went wrong." };
  }
}

// Keyed on the list and its filter, so unrelated route changes (e.g. leaving the page) do not refetch.
const loadKey = computed(() =>
  route.name === listRoute.value ? `${listRoute.value}|${serializePriorityFilter(filter.value) ?? ""}` : null
);
watch(loadKey, (key) => key !== null && load(key), { immediate: true });

// --- Actions on a card ---------------------------------------------------------------------

const busyId = ref<number | null>(null);
const actionError = ref<string | null>(null);
const pendingDelete = ref<Note | null>(null);

function removeFromList(id: number): void {
  if (state.value.status === "ready") state.value.notes = state.value.notes.filter((n) => n.id !== id);
}

/** Runs one card action; a note that is already gone elsewhere just leaves the list. */
async function act(note: Note, action: () => Promise<unknown>, done: string): Promise<boolean> {
  busyId.value = note.id;
  actionError.value = null;
  try {
    await action();
    removeFromList(note.id);
    flash.show(done);
    return true;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      removeFromList(note.id);
      actionError.value = `“${note.title}” no longer exists. It was probably deleted somewhere else.`;
    } else if (!(error instanceof ApiError && error.status === 401)) {
      actionError.value = error instanceof ApiError ? error.message : "Something went wrong. Please try again.";
    }
    return false;
  } finally {
    busyId.value = null;
  }
}

function archive(note: Note): void {
  void act(note, () => notesApi.archive(note.id), "Note archived. Find it under Archived.");
}

function restore(note: Note): void {
  void act(note, () => notesApi.restore(note.id), "Note restored.");
}

async function confirmDelete(): Promise<void> {
  const note = pendingDelete.value;
  if (!note) return;
  await act(note, () => notesApi.delete(note.id), "Note deleted.");
  pendingDelete.value = null;
}
</script>

<template>
  <section class="list-view" aria-labelledby="list-heading">
    <div class="toolbar">
      <h1 id="list-heading">{{ archived ? "Archive" : "Notes" }}</h1>
      <RouterLink class="button button--primary" :to="{ name: 'note-new' }">New note</RouterLink>
    </div>

    <nav class="tabs" aria-label="Which notes">
      <RouterLink class="tab" :to="{ name: 'notes', query: route.query }" :aria-current="archived ? undefined : 'page'">Active</RouterLink>
      <RouterLink class="tab" :to="{ name: 'archive', query: route.query }" :aria-current="archived ? 'page' : undefined">
        Archived
      </RouterLink>
    </nav>

    <PriorityFilter v-model="filter" />

    <p v-if="actionError" class="status status--error status--inline" role="alert">{{ actionError }}</p>

    <p v-if="state.status === 'loading'" class="status" role="status">Loading notes…</p>

    <div v-else-if="state.status === 'error'" class="status status--error" role="alert">
      <p>{{ state.message }}</p>
      <button type="button" class="button" @click="loadKey && load(loadKey)">Try again</button>
    </div>

    <template v-else>
      <p class="sr-only" role="status">{{ state.notes.length }} {{ state.notes.length === 1 ? "note" : "notes" }} shown</p>
      <div v-if="state.notes.length === 0" class="empty">
        <template v-if="filter.length > 0">
          <p>{{ archived ? "No archived notes with this priority." : "No notes with this priority." }}</p>
          <button type="button" class="button" @click="filter = []">Show all priorities</button>
        </template>
        <p v-else-if="archived">Nothing archived. Archive a note to keep it out of your list without deleting it.</p>
        <template v-else>
          <p>No notes yet. Write down the first thing on your mind.</p>
          <RouterLink class="button button--primary" :to="{ name: 'note-new' }">Create your first note</RouterLink>
        </template>
      </div>
      <ul v-else class="grid" :aria-label="archived ? 'Archived notes' : 'Notes'">
        <li v-for="note in state.notes" :key="note.id">
          <NoteCard
            :note="note"
            :busy="busyId === note.id"
            @archive="archive(note)"
            @restore="restore(note)"
            @delete="pendingDelete = note"
          />
        </li>
      </ul>
    </template>

    <ConfirmDialog
      :open="pendingDelete !== null"
      title="Delete this note?"
      confirm-label="Delete permanently"
      :busy="busyId !== null"
      @confirm="confirmDelete"
      @cancel="pendingDelete = null"
    >
      <p>
        “{{ pendingDelete?.title }}” will be deleted for good. This cannot be undone.
        <template v-if="!archived">To keep it out of the way instead, archive it.</template>
      </p>
    </ConfirmDialog>
  </section>
</template>

<style scoped>
.list-view {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}
.toolbar h1 {
  margin: 0;
}
.tabs {
  display: flex;
  gap: 0.25rem;
  border-bottom: 1px solid var(--border);
}
.tab {
  padding: 0.5rem 0.9rem;
  margin-bottom: -1px;
  color: var(--text-muted);
  text-decoration: none;
  font-weight: 600;
  border-bottom: 3px solid transparent;
}
.tab:hover {
  color: var(--text);
}
.tab[aria-current="page"] {
  color: var(--accent);
  border-bottom-color: var(--accent);
}
.grid {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
  gap: 1rem;
}
.grid > li {
  display: flex;
}
.grid > li > * {
  flex: 1;
}
.status,
.empty {
  padding: 2.5rem 1rem;
  text-align: center;
  color: var(--text-muted);
  border: 1px dashed var(--border-strong);
  border-radius: var(--radius);
}
.status--error {
  color: var(--danger);
  border-color: var(--danger);
}
.status--inline {
  margin: 0;
  padding: 0.75rem 1rem;
  text-align: left;
  border-style: solid;
  background: var(--danger-bg);
}
.empty p,
.status p {
  margin: 0 0 1rem;
}
.empty p:last-child {
  margin-bottom: 0;
}
</style>
