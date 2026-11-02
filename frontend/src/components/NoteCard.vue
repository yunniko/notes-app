<script setup lang="ts">
import type { Note } from "@/domain/note";
import { formatDateTime } from "@/domain/format";
import PriorityBadge from "./PriorityBadge.vue";

defineProps<{ note: Note }>();
</script>

<template>
  <article class="card" :data-priority="note.priority">
    <header class="card__header">
      <h2 class="card__title">
        <RouterLink :to="{ name: 'note-edit', params: { id: note.id } }">{{ note.title }}</RouterLink>
      </h2>
      <PriorityBadge :priority="note.priority" />
    </header>
    <!-- Text interpolation only (never v-html): note content is user input. -->
    <p v-if="note.content" class="card__content">{{ note.content }}</p>
    <p v-else class="card__content card__content--empty">No content</p>
    <footer class="card__meta">
      Updated <time :datetime="note.updatedAt">{{ formatDateTime(note.updatedAt) }}</time>
    </footer>
  </article>
</template>

<style scoped>
.card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem 1.1rem;
  background: var(--surface);
  border: 1px solid var(--border);
  border-left: 4px solid var(--priority-color, var(--border));
  border-radius: var(--radius);
  transition: box-shadow 0.15s;
}
.card:hover,
.card:focus-within {
  box-shadow: var(--shadow);
}
.card[data-priority="high"] {
  --priority-color: var(--priority-high);
}
.card[data-priority="medium"] {
  --priority-color: var(--priority-medium);
}
.card[data-priority="low"] {
  --priority-color: var(--priority-low);
}
.card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}
.card__title {
  margin: 0;
  font-size: 1.05rem;
  line-height: 1.35;
  overflow-wrap: anywhere;
}
.card__title a {
  color: inherit;
  text-decoration: none;
}
/* The whole card is clickable through the title link's stretched hit area. */
.card__title a::after {
  content: "";
  position: absolute;
  inset: 0;
}
.card__title a:focus-visible {
  outline: none;
}
.card:has(a:focus-visible) {
  outline: 3px solid var(--focus);
  outline-offset: 2px;
}
.card__content {
  margin: 0;
  color: var(--text-muted);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  display: -webkit-box;
  -webkit-line-clamp: 4;
  line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.card__content--empty {
  font-style: italic;
}
.card__meta {
  margin-top: auto;
  font-size: 0.8rem;
  color: var(--text-muted);
}
</style>
