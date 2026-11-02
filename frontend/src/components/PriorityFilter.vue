<script setup lang="ts">
import { PRIORITIES, PRIORITY_LABELS, type Priority } from "@/domain/priority";

/**
 * Multi-select priority filter. An empty selection means "all priorities".
 * Each option is a toggle button (aria-pressed), so it works by keyboard and screen reader.
 */
const selected = defineModel<Priority[]>({ required: true });

function toggle(priority: Priority): void {
  selected.value = selected.value.includes(priority) ? selected.value.filter((p) => p !== priority) : [...selected.value, priority];
}
</script>

<template>
  <div class="filter" role="group" aria-label="Filter by priority">
    <button type="button" class="chip" :aria-pressed="selected.length === 0" @click="selected = []">All</button>
    <button
      v-for="priority in PRIORITIES"
      :key="priority"
      type="button"
      class="chip"
      :class="`chip--${priority}`"
      :aria-pressed="selected.includes(priority)"
      @click="toggle(priority)"
    >
      {{ PRIORITY_LABELS[priority] }}
    </button>
  </div>
</template>

<style scoped>
.filter {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.chip {
  padding: 0.4rem 0.9rem;
  border-radius: 999px;
  border: 1px solid var(--border-strong);
  background: var(--surface);
  color: var(--text);
  font: inherit;
  font-size: 0.9rem;
  cursor: pointer;
}
.chip:hover {
  border-color: var(--accent);
}
.chip[aria-pressed="true"] {
  background: var(--accent);
  border-color: var(--accent);
  color: var(--accent-contrast);
}
</style>
