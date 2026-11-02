<script setup lang="ts">
import { useTemplateRef, watch } from "vue";

/**
 * A modal yes/no question on the native <dialog>: it traps focus, closes on Escape and
 * returns focus to the button that opened it, without a library.
 */
const props = defineProps<{ open: boolean; title: string; confirmLabel: string; busy?: boolean }>();
const emit = defineEmits<{ confirm: []; cancel: [] }>();
const dialog = useTemplateRef<HTMLDialogElement>("dialog");

watch(
  [() => props.open, dialog],
  ([open, element]) => {
    if (!element) return;
    if (open && !element.open) {
      element.showModal();
    } else if (!open && element.open) {
      element.close();
    }
  },
  { immediate: true }
);

function onCancel(event: Event): void {
  event.preventDefault(); // Escape: let the parent close it, so `open` stays the one source of truth
  if (!props.busy) emit("cancel");
}
</script>

<template>
  <dialog ref="dialog" class="dialog" aria-labelledby="confirm-title" aria-describedby="confirm-body" @cancel="onCancel">
    <h2 id="confirm-title" class="dialog__title">{{ title }}</h2>
    <div id="confirm-body" class="dialog__body"><slot /></div>
    <div class="dialog__actions">
      <button type="button" class="button" :disabled="busy" autofocus @click="emit('cancel')">Cancel</button>
      <button type="button" class="button button--danger" :disabled="busy" @click="emit('confirm')">
        {{ busy ? "Working…" : confirmLabel }}
      </button>
    </div>
  </dialog>
</template>

<style scoped>
.dialog {
  width: min(28rem, calc(100vw - 2rem));
  padding: 1.25rem 1.4rem;
  color: var(--text);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
}
.dialog::backdrop {
  background: rgb(0 0 0 / 0.45);
}
.dialog__title {
  margin: 0 0 0.6rem;
  font-size: 1.15rem;
}
.dialog__body {
  color: var(--text-muted);
  overflow-wrap: anywhere;
}
.dialog__body :deep(p) {
  margin: 0;
}
.dialog__actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 1.25rem;
}
</style>
