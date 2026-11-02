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
  <dialog ref="dialog" class="dialog sheet paper" aria-labelledby="confirm-title" aria-describedby="confirm-body" @cancel="onCancel">
    <h2 id="confirm-title" class="dialog__title">{{ title }}</h2>
    <div id="confirm-body" class="dialog__body"><slot /></div>
    <div class="dialog__actions">
      <button type="button" class="sb" :disabled="busy" autofocus @click="emit('cancel')">Cancel</button>
      <button type="button" class="sb sb--danger" :disabled="busy" @click="emit('confirm')">
        {{ busy ? "Working…" : confirmLabel }}
      </button>
    </div>
  </dialog>
</template>

<style scoped>
.dialog {
  width: min(28rem, calc(100vw - 2rem));
  padding: 30px 30px 24px;
  gap: 12px;
}
/* `.sheet` sets display and position, and the global reset drops the browser's `margin: auto`: a dialog
   must stay hidden while closed, and fixed in the middle of the viewport while open. */
.dialog {
  position: fixed;
  inset: 0;
  margin: auto;
  height: fit-content;
  max-height: calc(100dvh - 2rem);
  overflow: auto;
}
.dialog:not([open]) {
  display: none;
}
.dialog::backdrop {
  background: rgb(4 8 16 / 0.7);
}
.dialog__title {
  margin: 0;
  font: 600 26px/1.15 var(--serif);
  color: var(--ink);
}
.dialog__body {
  font: 400 15px/1.6 var(--sans);
  color: var(--ink-body);
  overflow-wrap: anywhere;
}
.dialog__body :deep(p) {
  margin: 0;
}
.dialog__actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}
</style>
