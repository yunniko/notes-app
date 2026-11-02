<script setup lang="ts">
import { PRIORITIES, PRIORITY_LABELS, type Priority } from "@/domain/priority";

/** The three priority picks of a note form: toggle buttons, exactly one pressed. */
const priority = defineModel<Priority>({ required: true });
</script>

<template>
  <div class="picks" role="group" aria-label="Priority">
    <button
      v-for="option in PRIORITIES"
      :key="option"
      type="button"
      class="dp"
      :class="`dp--${option}`"
      :aria-pressed="priority === option ? 'true' : 'false'"
      :aria-label="`${PRIORITY_LABELS[option]} priority`"
      @click="priority = option"
    >
      <span class="dot" aria-hidden="true"></span>{{ PRIORITY_LABELS[option] }}
    </button>
  </div>
</template>

<style scoped>
.picks {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
</style>
