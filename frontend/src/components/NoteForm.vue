<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef } from "vue";
import { CONTENT_MAX_LENGTH, TITLE_MAX_LENGTH, characterCount, validateNoteInput, type FieldErrors, type NoteInput } from "@/domain/note";
import { PRIORITIES, PRIORITY_LABELS } from "@/domain/priority";

/**
 * The note editor fields. The parent owns the data (v-model) and the saving; this component
 * owns field-level validation display. `serverErrors` come from a 422 response.
 */
const note = defineModel<NoteInput>({ required: true });
const props = defineProps<{ serverErrors?: FieldErrors; submitting?: boolean; submitLabel: string }>();
const emit = defineEmits<{ submit: [input: NoteInput]; cancel: [] }>();

const attempted = ref(false);
const titleInput = useTemplateRef<HTMLInputElement>("title");
const contentInput = useTemplateRef<HTMLTextAreaElement>("content");

const clientErrors = computed(() => validateNoteInput(note.value));
/** Client errors show once the user has tried to submit; server errors always show. */
const errors = computed<FieldErrors>(() => ({ ...props.serverErrors, ...(attempted.value ? clientErrors.value : {}) }));
const titleCount = computed(() => characterCount(note.value.title.trim()));
const contentCount = computed(() => characterCount(note.value.content));

async function onSubmit(): Promise<void> {
  attempted.value = true;
  if (Object.keys(clientErrors.value).length > 0) {
    await nextTick();
    (clientErrors.value.title ? titleInput.value : contentInput.value)?.focus();
    return;
  }
  emit("submit", { ...note.value, title: note.value.title.trim() });
}
</script>

<template>
  <form class="form" novalidate @submit.prevent="onSubmit">
    <div class="field">
      <label for="note-title">Title</label>
      <input
        id="note-title"
        ref="title"
        v-model="note.title"
        type="text"
        autocomplete="off"
        required
        :aria-invalid="!!errors.title"
        :aria-describedby="errors.title ? 'note-title-error' : 'note-title-count'"
      />
      <div class="field__hint">
        <p v-if="errors.title" id="note-title-error" class="field__error" role="alert">{{ errors.title }}</p>
        <span id="note-title-count" class="field__count" :class="{ 'field__count--over': titleCount > TITLE_MAX_LENGTH }">
          {{ titleCount }} / {{ TITLE_MAX_LENGTH }}
        </span>
      </div>
    </div>

    <fieldset class="field">
      <legend>Priority</legend>
      <div class="priority-options">
        <label v-for="priority in PRIORITIES" :key="priority" class="priority-option" :class="`priority-option--${priority}`">
          <input v-model="note.priority" type="radio" name="priority" :value="priority" />
          {{ PRIORITY_LABELS[priority] }}
        </label>
      </div>
      <p v-if="errors.priority" class="field__error" role="alert">{{ errors.priority }}</p>
    </fieldset>

    <div class="field">
      <label for="note-content">Content <span class="field__optional">(optional)</span></label>
      <textarea
        id="note-content"
        ref="content"
        v-model="note.content"
        rows="10"
        :aria-invalid="!!errors.content"
        :aria-describedby="errors.content ? 'note-content-error' : undefined"
      ></textarea>
      <div class="field__hint">
        <p v-if="errors.content" id="note-content-error" class="field__error" role="alert">{{ errors.content }}</p>
        <span
          v-if="contentCount > CONTENT_MAX_LENGTH * 0.9"
          class="field__count"
          :class="{ 'field__count--over': contentCount > CONTENT_MAX_LENGTH }"
        >
          {{ contentCount }} / {{ CONTENT_MAX_LENGTH }}
        </span>
      </div>
    </div>

    <div class="actions">
      <button type="submit" class="button button--primary" :disabled="submitting">
        {{ submitting ? "Saving…" : submitLabel }}
      </button>
      <button type="button" class="button" :disabled="submitting" @click="emit('cancel')">Cancel</button>
    </div>
  </form>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  border: 0;
  margin: 0;
  padding: 0;
}
.field > label,
.field > legend {
  font-weight: 600;
  padding: 0;
}
.field__optional {
  font-weight: 400;
  color: var(--text-muted);
}
.field input[type="text"],
.field textarea {
  width: 100%;
  padding: 0.6rem 0.75rem;
  font: inherit;
  color: var(--text);
  background: var(--surface);
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
}
.field textarea {
  resize: vertical;
  min-height: 8rem;
  line-height: 1.5;
}
.field [aria-invalid="true"] {
  border-color: var(--danger);
}
.field__hint {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  min-height: 1.2rem;
  font-size: 0.85rem;
}
.field__error {
  margin: 0;
  color: var(--danger);
}
.field__count {
  margin-left: auto;
  color: var(--text-muted);
  font-variant-numeric: tabular-nums;
}
.field__count--over {
  color: var(--danger);
  font-weight: 600;
}
.priority-options {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.priority-option {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.9rem;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  font-weight: 500;
  cursor: pointer;
}
.priority-option:has(input:checked) {
  border-color: var(--priority-color);
  background: var(--priority-bg);
  color: var(--priority-color);
  font-weight: 600;
}
.priority-option:has(input:focus-visible) {
  outline: 3px solid var(--focus);
  outline-offset: 2px;
}
.priority-option--high {
  --priority-color: var(--priority-high);
  --priority-bg: var(--priority-high-bg);
}
.priority-option--medium {
  --priority-color: var(--priority-medium);
  --priority-bg: var(--priority-medium-bg);
}
.priority-option--low {
  --priority-color: var(--priority-low);
  --priority-bg: var(--priority-low-bg);
}
.priority-option input {
  accent-color: var(--priority-color);
}
.actions {
  display: flex;
  gap: 0.75rem;
}
</style>
