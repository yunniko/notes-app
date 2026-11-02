<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { ApiError, notesApi } from "@/api/notes";
import NoteCard from "@/components/NoteCard.vue";
import PriorityFilter from "@/components/PriorityFilter.vue";
import type { Note } from "@/domain/note";
import { parsePriorityFilter, serializePriorityFilter, type Priority } from "@/domain/priority";

const route = useRoute();
const router = useRouter();

type State = { status: "loading" } | { status: "error"; message: string } | { status: "ready"; notes: Note[] };
const state = ref<State>({ status: "loading" });

/** The filter lives in the URL, so it survives reload and can be bookmarked or shared. */
const filter = computed<Priority[]>({
  get: () => parsePriorityFilter(route.query.priority),
  set: (priorities) => router.replace({ query: { ...route.query, priority: serializePriorityFilter(priorities) } }),
});

let controller: AbortController | undefined;

async function load(priorities: Priority[]): Promise<void> {
  controller?.abort(); // a newer filter wins over a slower, older response
  controller = new AbortController();
  const { signal } = controller;
  if (state.value.status !== "ready") state.value = { status: "loading" };
  try {
    const notes = await notesApi.list(priorities, signal);
    state.value = { status: "ready", notes };
  } catch (error) {
    if (signal.aborted) return;
    state.value = { status: "error", message: error instanceof ApiError ? error.message : "Something went wrong." };
  }
}

// Keyed on the serialized filter, so unrelated route changes (e.g. leaving the page) do not refetch.
watch(
  () => (route.name === "notes" ? (serializePriorityFilter(filter.value) ?? "") : null),
  (key) => key !== null && load(filter.value),
  { immediate: true }
);
</script>

<template>
  <section class="list-view" aria-labelledby="list-heading">
    <div class="toolbar">
      <h1 id="list-heading">Notes</h1>
      <RouterLink class="button button--primary" :to="{ name: 'note-new' }">New note</RouterLink>
    </div>

    <PriorityFilter v-model="filter" />

    <p v-if="state.status === 'loading'" class="status" role="status">Loading notes…</p>

    <div v-else-if="state.status === 'error'" class="status status--error" role="alert">
      <p>{{ state.message }}</p>
      <button type="button" class="button" @click="load(filter)">Try again</button>
    </div>

    <template v-else>
      <p class="sr-only" role="status">{{ state.notes.length }} {{ state.notes.length === 1 ? "note" : "notes" }} shown</p>
      <div v-if="state.notes.length === 0" class="empty">
        <template v-if="filter.length > 0">
          <p>No notes with this priority.</p>
          <button type="button" class="button" @click="filter = []">Show all notes</button>
        </template>
        <template v-else>
          <p>No notes yet. Write down the first thing on your mind.</p>
          <RouterLink class="button button--primary" :to="{ name: 'note-new' }">Create your first note</RouterLink>
        </template>
      </div>
      <ul v-else class="grid" aria-label="Notes">
        <li v-for="note in state.notes" :key="note.id">
          <NoteCard :note="note" />
        </li>
      </ul>
    </template>
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
.empty p,
.status p {
  margin: 0 0 1rem;
}
</style>
