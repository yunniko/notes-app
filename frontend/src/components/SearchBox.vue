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

// The model can change from outside (Back button, a link): show what is actually applied.
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
    <svg
      width="17"
      height="17"
      viewBox="0 0 24 24"
      fill="none"
      stroke="#AEB6C2"
      stroke-width="1.7"
      stroke-linecap="round"
      aria-hidden="true"
    >
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </svg>
    <label class="sr-only" for="note-search">Search notes</label>
    <input
      id="note-search"
      v-model="text"
      class="search__input"
      type="search"
      placeholder="Search your notes"
      autocomplete="off"
      :maxlength="SEARCH_MAX_LENGTH"
      @input="onInput"
      @keydown.esc.prevent="clear"
    />
    <button v-if="text" type="button" class="search__clear" aria-label="Clear search text" title="Clear search (Esc)" @click="clear">
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
  </form>
</template>

<style scoped>
/* A pill on the dark header. */
.search {
  height: 44px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 4px 0 18px;
  background: rgb(0 0 0 / 0.22);
  border: 1px solid var(--silver-line);
  border-radius: 999px;
}
.search:focus-within {
  border-color: var(--silver-light);
  box-shadow: 0 0 0 3px rgb(220 225 232 / 0.25);
}
.search__input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  font: 400 15px var(--sans);
  letter-spacing: 0.02em;
  color: var(--cream);
}
.search__input::placeholder {
  color: var(--placeholder-on-board);
  font-style: italic;
}
/* The browser's own clear control duplicates ours and is not keyboard reachable everywhere. */
.search__input::-webkit-search-cancel-button {
  appearance: none;
}
.search__clear {
  flex: none;
  width: 36px;
  height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--on-board-muted);
  cursor: pointer;
}
.search__clear:hover {
  background: rgb(255 255 255 / 0.08);
  color: var(--cream);
}
</style>
