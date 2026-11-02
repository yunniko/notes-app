<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from "vue";
import { SEARCH_MAX_LENGTH } from "@/domain/search";

/**
 * Search field that commits as you type, after a short pause, so the list (and the URL) does not
 * change on every keystroke. Enter commits at once; Escape or the clear button empties it.
 */
const search = defineModel<string>({ required: true });
const props = withDefaults(defineProps<{ debounceMs?: number }>(), { debounceMs: 300 });

const text = ref(search.value);
let timer: ReturnType<typeof setTimeout> | undefined;

// The model can change from outside (Back button, a tab link): show what is actually applied.
watch(search, (value) => {
  if (value !== text.value.trim()) text.value = value;
});

function commit(): void {
  clearTimeout(timer);
  const value = text.value.trim();
  if (value !== search.value) search.value = value;
}

function onInput(): void {
  clearTimeout(timer);
  timer = setTimeout(commit, props.debounceMs);
}

function clear(): void {
  text.value = "";
  commit();
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
  <form class="search" role="search" @submit.prevent="commit">
    <label class="sr-only" for="note-search">Search notes</label>
    <input
      id="note-search"
      v-model="text"
      class="search__input"
      type="search"
      placeholder="Search in titles and content"
      autocomplete="off"
      :maxlength="SEARCH_MAX_LENGTH"
      @input="onInput"
      @keydown.esc.prevent="clear"
    />
    <button v-if="text" type="button" class="search__clear" aria-label="Clear search text" @click="clear">×</button>
  </form>
</template>

<style scoped>
.search {
  position: relative;
  display: flex;
  max-width: 28rem;
}
.search__input {
  flex: 1;
  min-width: 0;
  padding: 0.55rem 2.4rem 0.55rem 0.9rem;
  font: inherit;
  color: var(--text);
  background: var(--surface);
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
}
.search__input:focus-visible {
  outline: 3px solid var(--focus);
  outline-offset: 1px;
}
/* The browser's own clear control duplicates ours and is not keyboard reachable everywhere. */
.search__input::-webkit-search-cancel-button {
  appearance: none;
}
.search__clear {
  position: absolute;
  right: 0.3rem;
  top: 50%;
  transform: translateY(-50%);
  width: 2rem;
  height: 2rem;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--text-muted);
  font-size: 1.3rem;
  line-height: 1;
  cursor: pointer;
}
.search__clear:hover {
  color: var(--text);
  background: var(--border);
}
</style>
