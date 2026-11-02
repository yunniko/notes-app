<script setup lang="ts">
import { PRIORITIES, PRIORITY_LABELS, type Priority } from "@/domain/priority";

/**
 * Multi-select priority filter on the board. Nothing pressed means "all priorities".
 * Each option is a toggle button (aria-pressed), so it works by keyboard and screen reader.
 */
const selected = defineModel<Priority[]>({ required: true });

function toggle(priority: Priority): void {
  selected.value = selected.value.includes(priority) ? selected.value.filter((p) => p !== priority) : [...selected.value, priority];
}
</script>

<template>
  <div class="track" role="group" aria-label="Filter by priority">
    <button
      v-for="priority in PRIORITIES"
      :key="priority"
      type="button"
      class="seg"
      :class="`seg--${priority}`"
      :aria-pressed="selected.includes(priority) ? 'true' : 'false'"
      @click="toggle(priority)"
    >
      <span class="dot" aria-hidden="true"></span>{{ PRIORITY_LABELS[priority] }}
    </button>
  </div>
</template>

<style scoped>
/* The dot is a light tint on the dark track and the full colour once pressed (design values). */
.seg--high .dot {
  color: var(--high-dot);
}
.seg--medium .dot {
  color: var(--medium-dot);
}
.seg--low .dot {
  color: var(--low-dot);
}
.seg--high[aria-pressed="true"] .dot {
  color: var(--high);
}
.seg--medium[aria-pressed="true"] .dot {
  color: var(--medium);
}
.seg--low[aria-pressed="true"] .dot {
  color: var(--low);
}
</style>
