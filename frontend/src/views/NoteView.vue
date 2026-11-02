<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { ApiError, notesApi } from "@/api/notes";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import PriorityBadge from "@/components/PriorityBadge.vue";
import { useListMemory } from "@/composables/useListMemory";
import { useNoteActions } from "@/composables/useNoteActions";
import { formatDateTime } from "@/domain/format";
import type { Note } from "@/domain/note";

/** A note, read-only. Editing is one click away (Edit), so reading cannot change it by accident. */
const props = defineProps<{ id: number }>();
const router = useRouter();
const lists = useListMemory();

type State = { status: "loading" } | { status: "error"; error: ApiError } | { status: "ready"; note: Note };
const state = ref<State>({ status: "loading" });
const note = computed(() => (state.value.status === "ready" ? state.value.note : null));
const listName = computed(() => (note.value?.archivedAt ? "archive" : "notes"));

watch(
  () => props.id,
  async (id) => {
    state.value = { status: "loading" };
    try {
      state.value = { status: "ready", note: await notesApi.get(id) };
    } catch (error) {
      state.value = { status: "error", error: error instanceof ApiError ? error : new ApiError("Something went wrong.", 0) };
    }
  },
  { immediate: true }
);

const actions = useNoteActions({
  changed: (updated) => (state.value = { status: "ready", note: updated }),
  deleted: (deleted) => void router.push(lists.backTo(deleted.archivedAt ? "archive" : "notes")),
  gone: () => (state.value = { status: "error", error: new ApiError("Not found.", 404) }),
});
</script>

<template>
  <section class="note-view" aria-labelledby="note-heading">
    <RouterLink class="back" :to="lists.backTo(listName)">{{ listName === "archive" ? "← Archive" : "← All notes" }}</RouterLink>

    <p v-if="state.status === 'loading'" role="status">Loading note…</p>

    <div v-else-if="state.status === 'error'" class="notice notice--error" role="alert">
      <p v-if="state.error.status === 404">This note does not exist. It may have been removed, or the link is wrong.</p>
      <p v-else>{{ state.error.message }}</p>
      <RouterLink class="button" :to="{ name: 'notes' }">Back to all notes</RouterLink>
    </div>

    <template v-else-if="note">
      <header class="note-view__header">
        <h1 id="note-heading">{{ note.title }}</h1>
        <PriorityBadge :priority="note.priority" />
      </header>

      <div class="note-view__actions">
        <RouterLink class="button button--primary" :to="{ name: 'note-edit', params: { id: note.id } }">Edit</RouterLink>
        <button
          v-if="note.archivedAt"
          type="button"
          class="button"
          :disabled="actions.busyId.value !== null"
          @click="actions.restore(note)"
        >
          Restore
        </button>
        <button v-else type="button" class="button" :disabled="actions.busyId.value !== null" @click="actions.archive(note)">
          Archive
        </button>
        <button
          type="button"
          class="button note-view__delete"
          :disabled="actions.busyId.value !== null"
          @click="actions.requestDelete(note)"
        >
          Delete
        </button>
      </div>

      <p v-if="actions.error.value" class="notice notice--error" role="alert">{{ actions.error.value }}</p>
      <p v-if="note.archivedAt" class="notice notice--info">
        Archived {{ formatDateTime(note.archivedAt) }}. It is kept out of your notes list; Restore brings it back.
      </p>

      <!-- Text interpolation only (never v-html): note content is user input. -->
      <div v-if="note.content" class="note-view__content" aria-label="Content">{{ note.content }}</div>
      <p v-else class="note-view__content note-view__content--empty">No content</p>

      <p class="meta">
        Created <time :datetime="note.createdAt">{{ formatDateTime(note.createdAt) }}</time> · last updated
        <time :datetime="note.updatedAt">{{ formatDateTime(note.updatedAt) }}</time>
      </p>
    </template>

    <ConfirmDialog
      :open="actions.pendingDelete.value !== null"
      title="Delete this note?"
      confirm-label="Delete permanently"
      :busy="actions.busyId.value !== null"
      @confirm="actions.confirmDelete"
      @cancel="actions.cancelDelete"
    >
      <p>
        “{{ actions.pendingDelete.value?.title }}” will be deleted for good. This cannot be undone.
        <template v-if="!note?.archivedAt">To keep it out of the way instead, archive it.</template>
      </p>
    </ConfirmDialog>
  </section>
</template>

<style scoped>
.note-view {
  max-width: 44rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.back {
  color: var(--text-muted);
  text-decoration: none;
  font-size: 0.9rem;
}
.back:hover {
  color: var(--accent);
}
.note-view__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}
.note-view__header h1 {
  margin: 0;
  overflow-wrap: anywhere;
}
.note-view__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.note-view__delete:hover:not(:disabled) {
  border-color: var(--danger);
  color: var(--danger);
}
.note-view__content {
  margin: 0;
  padding: 1rem 1.1rem;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  line-height: 1.6;
}
.note-view__content--empty {
  color: var(--text-muted);
  font-style: italic;
}
.notice {
  margin: 0;
  padding: 0.9rem 1rem;
  border-radius: var(--radius-sm);
  border: 1px solid;
}
.notice p {
  margin: 0 0 0.75rem;
}
.notice--error {
  color: var(--danger);
  border-color: var(--danger);
  background: var(--danger-bg);
}
.notice--info {
  color: var(--text-muted);
  border-color: var(--border-strong);
  background: var(--surface);
}
.meta {
  margin: 0;
  font-size: 0.85rem;
  color: var(--text-muted);
}
</style>
