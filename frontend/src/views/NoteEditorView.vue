<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { onBeforeRouteLeave, useRouter, type RouteLocationRaw } from "vue-router";
import { ApiError, notesApi } from "@/api/notes";
import NoteForm from "@/components/NoteForm.vue";
import { useFlash } from "@/composables/useFlash";
import { useListMemory } from "@/composables/useListMemory";
import { formatDateTime } from "@/domain/format";
import type { FieldErrors, Note, NoteInput } from "@/domain/note";

/** Creates a note when `id` is absent, edits note `id` otherwise. Leaving returns to the read-only note page. */
const props = defineProps<{ id?: number }>();
const router = useRouter();
const flash = useFlash();
const lists = useListMemory();

const isNew = computed(() => props.id === undefined);
const loading = ref(!isNew.value);
const loadError = ref<ApiError | null>(null);
const saving = ref(false);
const saveError = ref<string | null>(null);
const serverErrors = ref<FieldErrors>({});

/** The stored note this edit started from (its version is sent back on save). */
const original = ref<Note | null>(null);
/** Set when a save hit a 409: the note as someone else saved it meanwhile. */
const conflict = ref<Note | null>(null);
/** Where Cancel and the back link go: the note itself, or the list for a note not yet created. */
const leaveTo = computed<RouteLocationRaw>(() =>
  props.id === undefined ? lists.backTo("notes") : { name: "note", params: { id: props.id } }
);

const emptyInput = (): NoteInput => ({ title: "", content: "", priority: "medium" });
const form = ref<NoteInput>(emptyInput());
const baseline = ref<NoteInput>(emptyInput());

const dirty = computed(
  () =>
    form.value.title !== baseline.value.title ||
    form.value.content !== baseline.value.content ||
    form.value.priority !== baseline.value.priority
);

function startFrom(note: Note): void {
  original.value = note;
  const input = { title: note.title, content: note.content, priority: note.priority };
  form.value = { ...input };
  baseline.value = { ...input };
}

watch(
  () => props.id,
  async (id) => {
    if (id === undefined) return;
    loading.value = true;
    loadError.value = null;
    try {
      startFrom(await notesApi.get(id));
    } catch (error) {
      loadError.value = error instanceof ApiError ? error : new ApiError("Something went wrong.", 0);
    } finally {
      loading.value = false;
    }
  },
  { immediate: true }
);

async function save(input: NoteInput, version = original.value?.version): Promise<void> {
  saving.value = true;
  saveError.value = null;
  serverErrors.value = {};
  try {
    let saved: Note | undefined;
    if (isNew.value) {
      saved = await notesApi.create(input);
      flash.show("Note created.");
    } else if (props.id !== undefined && version !== undefined) {
      saved = await notesApi.update(props.id, input, version);
      flash.show("Note saved.");
    }
    baseline.value = { ...form.value }; // nothing unsaved any more: leave without the prompt
    // Replace, so Back from the note page skips the form that was just submitted.
    if (saved) await router.replace({ name: "note", params: { id: saved.id } });
  } catch (error) {
    if (error instanceof ApiError && error.isConflict && error.current) {
      conflict.value = error.current;
    } else if (error instanceof ApiError && error.status === 422) {
      serverErrors.value = error.fieldErrors;
      saveError.value = "Please fix the highlighted fields.";
    } else {
      saveError.value = error instanceof ApiError ? error.message : "Saving failed. Please try again.";
    }
  } finally {
    saving.value = false;
  }
}

/** Conflict resolution 1: discard my edits and continue from the newer stored version. */
function useTheirs(): void {
  if (conflict.value) startFrom(conflict.value);
  conflict.value = null;
}

/** Conflict resolution 2: save my edits on top of the newer version, knowingly replacing it. */
function keepMine(): void {
  const newer = conflict.value;
  conflict.value = null;
  if (newer) {
    original.value = newer;
    void save(form.value, newer.version);
  }
}

function cancel(): void {
  void (isNew.value ? router.push(leaveTo.value) : router.replace(leaveTo.value));
}

// Unsaved-changes protection, both for in-app navigation and for closing the tab.
onBeforeRouteLeave(() => !dirty.value || window.confirm("You have unsaved changes. Leave without saving?"));
const onBeforeUnload = (event: BeforeUnloadEvent) => {
  if (dirty.value) event.preventDefault();
};
onMounted(() => window.addEventListener("beforeunload", onBeforeUnload));
onBeforeUnmount(() => window.removeEventListener("beforeunload", onBeforeUnload));
</script>

<template>
  <section class="editor" aria-labelledby="editor-heading">
    <RouterLink class="back" :to="leaveTo">{{ isNew ? "← All notes" : "← Back to note" }}</RouterLink>
    <h1 id="editor-heading">{{ isNew ? "New note" : "Edit note" }}</h1>

    <p v-if="loading" role="status">Loading note…</p>

    <div v-else-if="loadError" class="notice notice--error" role="alert">
      <p v-if="loadError.status === 404">This note does not exist. It may have been removed, or the link is wrong.</p>
      <p v-else>{{ loadError.message }}</p>
      <RouterLink class="button" :to="{ name: 'notes' }">Back to all notes</RouterLink>
    </div>

    <template v-else>
      <div v-if="conflict" class="notice notice--warning" role="alert">
        <p>
          <strong>This note was changed somewhere else</strong> (saved {{ formatDateTime(conflict.updatedAt) }}), after you opened it. Your
          edits are not saved yet.
        </p>
        <details>
          <summary>Show the other version</summary>
          <dl class="conflict">
            <dt>Title</dt>
            <dd>{{ conflict.title }}</dd>
            <dt>Priority</dt>
            <dd>{{ conflict.priority }}</dd>
            <dt>Content</dt>
            <dd class="conflict__content">{{ conflict.content || "(empty)" }}</dd>
          </dl>
        </details>
        <div class="notice__actions">
          <button type="button" class="button" @click="useTheirs">Discard mine, load theirs</button>
          <button type="button" class="button button--primary" @click="keepMine">Save mine anyway</button>
        </div>
      </div>

      <p v-if="original?.archivedAt" class="notice notice--info">
        This note is archived. Saving keeps it archived; Restore on the note page brings it back.
      </p>

      <p v-if="saveError" class="notice notice--error" role="alert">{{ saveError }}</p>

      <NoteForm
        v-model="form"
        :server-errors="serverErrors"
        :submitting="saving"
        :submit-label="isNew ? 'Create note' : 'Save changes'"
        @submit="save"
        @cancel="cancel"
      />

      <p v-if="original" class="meta">
        Created {{ formatDateTime(original.createdAt) }} · last updated {{ formatDateTime(original.updatedAt) }}
      </p>
    </template>
  </section>
</template>

<style scoped>
.editor {
  max-width: 44rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.editor h1 {
  margin: 0;
}
.back {
  color: var(--text-muted);
  text-decoration: none;
  font-size: 0.9rem;
}
.back:hover {
  color: var(--accent);
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
.notice p:last-child {
  margin-bottom: 0;
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
.notice--warning {
  border-color: var(--warning);
  background: var(--warning-bg);
}
.notice__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.75rem;
}
.conflict {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 0.25rem 1rem;
  margin: 0.75rem 0 0;
}
.conflict dt {
  font-weight: 600;
}
.conflict dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.conflict__content {
  white-space: pre-wrap;
  max-height: 12rem;
  overflow: auto;
}
.meta {
  margin: 0;
  font-size: 0.85rem;
  color: var(--text-muted);
}
</style>
