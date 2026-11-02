<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from "vue";
import { ApiError, notesApi } from "@/api/notes";
import PriorityPicks from "@/components/PriorityPicks.vue";
import { formatDateTime } from "@/domain/format";
import { TITLE_MAX_LENGTH, validateNoteInput, type FieldErrors, type Note, type NoteInput } from "@/domain/note";
import { PRIORITY_LABELS, type Priority } from "@/domain/priority";

/**
 * Edits a note in place on its card. The version the edit started from goes back with the save;
 * if someone saved the note meanwhile (409), the card shows their version and lets the user choose.
 */
const props = defineProps<{ note: Note }>();
const emit = defineEmits<{
  saved: [note: Note];
  cancel: [];
  /** The note no longer exists (deleted in another tab). */
  gone: [];
  /** Whether the form differs from the stored note; the wall guards against losing it. */
  dirty: [dirty: boolean];
  /** The priority being picked, so the sheet can take its colour while editing. */
  priority: [priority: Priority];
}>();

const toInput = (note: Note): NoteInput => ({ title: note.title, content: note.content, priority: note.priority });
const base = ref<Note>(props.note);
const form = ref<NoteInput>(toInput(props.note));
const attempted = ref(false);
const saving = ref(false);
const serverErrors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
/** Set when a save hit a 409: the note as someone else saved it meanwhile. */
const conflict = ref<Note | null>(null);

const titleInput = useTemplateRef<HTMLInputElement>("title");
const ids = computed(() => ({
  title: `edit-title-${props.note.id}`,
  content: `edit-content-${props.note.id}`,
  titleError: `edit-title-error-${props.note.id}`,
  contentError: `edit-content-error-${props.note.id}`,
}));

const clientErrors = computed(() => validateNoteInput(form.value));
const errors = computed<FieldErrors>(() => ({ ...serverErrors.value, ...(attempted.value ? clientErrors.value : {}) }));
const dirty = computed(() => {
  const stored = toInput(base.value);
  return form.value.title !== stored.title || form.value.content !== stored.content || form.value.priority !== stored.priority;
});
const rows = computed(() => Math.min(12, Math.max(3, form.value.content.split("\n").length + 1)));

watch(dirty, (value) => emit("dirty", value), { immediate: true });
watch(
  () => form.value.priority,
  (value) => emit("priority", value),
  { immediate: true }
);

async function save(version = base.value.version): Promise<void> {
  attempted.value = true;
  serverErrors.value = {};
  formError.value = null;
  if (Object.keys(clientErrors.value).length > 0) return;

  saving.value = true;
  try {
    const saved = await notesApi.update(props.note.id, { ...form.value, title: form.value.title.trim() }, version);
    base.value = saved;
    emit("saved", saved);
  } catch (error) {
    if (error instanceof ApiError && error.isConflict && error.current) {
      conflict.value = error.current;
    } else if (error instanceof ApiError && error.status === 404) {
      emit("gone");
    } else if (error instanceof ApiError && error.status === 422) {
      serverErrors.value = error.fieldErrors;
    } else if (!(error instanceof ApiError && error.status === 401)) {
      formError.value = error instanceof ApiError ? error.message : "Saving failed. Please try again.";
    }
  } finally {
    saving.value = false;
  }
}

/** Conflict resolution 1: discard my edits and continue from the newer stored version. */
function loadTheirs(): void {
  if (conflict.value) {
    base.value = conflict.value;
    form.value = toInput(conflict.value);
  }
  conflict.value = null;
}

/** Conflict resolution 2: save my edits on top of the newer version, knowingly replacing it. */
function saveMine(): void {
  const newer = conflict.value;
  conflict.value = null;
  if (newer) {
    base.value = newer;
    void save(newer.version);
  }
}

onMounted(() => titleInput.value?.focus());
</script>

<template>
  <form class="edit" aria-label="Edit note" novalidate @submit.prevent="save()" @keydown.esc.prevent="emit('cancel')">
    <div v-if="conflict" class="sheet-notice sheet-notice--error edit__conflict" role="alert">
      <p>
        <strong>This note was changed somewhere else</strong> (saved {{ formatDateTime(conflict.updatedAt) }}). Your edits are not saved
        yet.
      </p>
      <details>
        <summary>Show the other version</summary>
        <dl>
          <dt>Title</dt>
          <dd>{{ conflict.title }}</dd>
          <dt>Priority</dt>
          <dd>{{ PRIORITY_LABELS[conflict.priority] }}</dd>
          <dt>Content</dt>
          <dd class="edit__their-content">{{ conflict.content || "(empty)" }}</dd>
        </dl>
      </details>
      <div class="edit__buttons">
        <button type="button" class="sb" @click="loadTheirs">Discard mine, load theirs</button>
        <button type="button" class="sb sb--primary" @click="saveMine">Save mine anyway</button>
      </div>
    </div>

    <label class="sr-only" :for="ids.title">Title</label>
    <input
      :id="ids.title"
      ref="title"
      v-model="form.title"
      class="line-field line-field--title"
      type="text"
      placeholder="Title"
      :maxlength="TITLE_MAX_LENGTH"
      :aria-invalid="errors.title ? 'true' : undefined"
      :aria-describedby="errors.title ? ids.titleError : undefined"
    />
    <p v-if="errors.title" :id="ids.titleError" class="field-error" role="alert">{{ errors.title }}</p>
    <label class="sr-only" :for="ids.content">Content</label>
    <textarea
      :id="ids.content"
      v-model="form.content"
      class="line-field"
      :rows="rows"
      placeholder="Take a note…"
      :aria-invalid="errors.content ? 'true' : undefined"
      :aria-describedby="errors.content ? ids.contentError : undefined"
    ></textarea>
    <p v-if="errors.content" :id="ids.contentError" class="field-error" role="alert">{{ errors.content }}</p>
    <PriorityPicks v-model="form.priority" />
    <p v-if="formError" class="sheet-notice sheet-notice--error" role="alert">{{ formError }}</p>
    <div class="edit__buttons">
      <button type="button" class="sb" :disabled="saving" @click="emit('cancel')">Cancel</button>
      <button type="submit" class="sb sb--primary" :disabled="saving || form.title.trim() === '' || conflict !== null">
        {{ saving ? "Saving…" : "Save" }}
      </button>
    </div>
  </form>
</template>

<style scoped>
.edit {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.edit__buttons {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 6px;
}
.edit__conflict {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.edit__conflict p,
.edit__conflict dl {
  margin: 0;
}
.edit__conflict summary {
  cursor: pointer;
}
.edit__conflict dt {
  font-weight: 600;
  margin-top: 6px;
}
.edit__conflict dd {
  margin: 0;
  color: var(--ink-body);
  overflow-wrap: anywhere;
}
.edit__their-content {
  white-space: pre-line;
}
</style>
