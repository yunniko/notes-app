<script setup lang="ts">
import { computed, ref, useTemplateRef } from "vue";
import { ApiError, notesApi } from "@/api/notes";
import PriorityPicks from "@/components/PriorityPicks.vue";
import { useComposer } from "@/composables/useComposer";
import { TITLE_MAX_LENGTH, validateNoteInput, type FieldErrors, type Note } from "@/domain/note";

/** The quick-note form at the start of the wall. The draft lives in useComposer, so it survives navigation. */
const emit = defineEmits<{ created: [note: Note]; close: [] }>();
const composer = useComposer();
const { draft } = composer;

const saving = ref(false);
const errors = ref<FieldErrors>({});
const formError = ref<string | null>(null);
const titleInput = useTemplateRef<HTMLInputElement>("title");

const canAdd = computed(() => draft.value.title.trim() !== "" && !saving.value);

async function add(): Promise<void> {
  const input = { ...draft.value, title: draft.value.title.trim() };
  errors.value = validateNoteInput(input);
  formError.value = null;
  if (Object.keys(errors.value).length > 0) return;

  saving.value = true;
  try {
    const note = await notesApi.create(input);
    composer.resetDraft();
    emit("created", note);
    titleInput.value?.focus();
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      errors.value = error.fieldErrors;
    } else if (!(error instanceof ApiError && error.status === 401)) {
      formError.value = error instanceof ApiError ? error.message : "Saving failed. Please try again.";
    }
  } finally {
    saving.value = false;
  }
}

defineExpose({ focus: (options?: FocusOptions) => titleInput.value?.focus(options) });
</script>

<template>
  <form class="composer sheet paper" aria-label="Quick note" novalidate @submit.prevent="add">
    <button type="button" class="ico composer__close" aria-label="Close quick note" title="Close" @click="emit('close')">
      <svg
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.2"
        stroke-linecap="round"
        aria-hidden="true"
      >
        <path d="M6 6l12 12M18 6L6 18" />
      </svg>
    </button>
    <label class="sr-only" for="quick-title">Title</label>
    <input
      id="quick-title"
      ref="title"
      v-model="draft.title"
      class="line-field line-field--title composer__title"
      type="text"
      placeholder="Title"
      :maxlength="TITLE_MAX_LENGTH"
      :aria-invalid="errors.title ? 'true' : undefined"
      :aria-describedby="errors.title ? 'quick-title-error' : undefined"
    />
    <p v-if="errors.title" id="quick-title-error" class="field-error" role="alert">{{ errors.title }}</p>
    <label class="sr-only" for="quick-content">Content</label>
    <textarea
      id="quick-content"
      v-model="draft.content"
      class="line-field"
      rows="3"
      placeholder="Take a note…"
      :aria-invalid="errors.content ? 'true' : undefined"
      :aria-describedby="errors.content ? 'quick-content-error' : undefined"
    ></textarea>
    <p v-if="errors.content" id="quick-content-error" class="field-error" role="alert">{{ errors.content }}</p>
    <p v-if="formError" class="sheet-notice sheet-notice--error" role="alert">{{ formError }}</p>
    <div class="composer__foot">
      <PriorityPicks v-model="draft.priority" />
      <button type="submit" class="sb sb--primary" :disabled="!canAdd">{{ saving ? "Adding…" : "Add note" }}</button>
    </div>
  </form>
</template>

<style scoped>
.composer {
  padding: 22px 20px 18px;
  background-color: var(--paper-cream);
}
.composer:focus-within {
  box-shadow:
    0 0 0 3px rgb(220 225 232 / 0.55),
    0 26px 40px -20px rgb(0 0 0 / 0.8);
}
.composer__close {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 1;
}
.composer__title {
  padding-right: 40px;
}
.composer__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
