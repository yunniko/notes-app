<script setup lang="ts">
import { NOTE_SORTS, SORT_LABELS, type NoteSort } from "@/domain/sort";

/** Newest / Oldest / Priority: one of them is always pressed. */
const sort = defineModel<NoteSort>({ required: true });
</script>

<template>
  <div class="track" role="group" aria-label="Sort notes">
    <button
      v-for="option in NOTE_SORTS"
      :key="option"
      type="button"
      class="seg"
      :aria-pressed="sort === option ? 'true' : 'false'"
      @click="sort = option"
    >
      {{ SORT_LABELS[option] }}
    </button>
  </div>
</template>
