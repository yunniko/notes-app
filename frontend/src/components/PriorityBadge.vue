<script setup lang="ts">
import { PRIORITY_LABELS, type Priority } from "@/domain/priority";

defineProps<{ priority: Priority }>();
</script>

<template>
  <span class="badge" :class="`badge--${priority}`">{{ PRIORITY_LABELS[priority] }}</span>
</template>

<style scoped>
.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35em;
  padding: 0.15em 0.6em;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 600;
  white-space: nowrap;
  border: 1px solid currentColor;
}
/* A dot before the label, so the priority does not rely on colour alone. */
.badge::before {
  content: "";
  width: 0.5em;
  height: 0.5em;
  border-radius: 50%;
  background: currentColor;
}
.badge--high {
  color: var(--priority-high);
  background: var(--priority-high-bg);
}
.badge--medium {
  color: var(--priority-medium);
  background: var(--priority-medium-bg);
}
.badge--low {
  color: var(--priority-low);
  background: var(--priority-low-bg);
}
</style>
