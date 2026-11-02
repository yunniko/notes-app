<script setup lang="ts">
import { computed, ref } from "vue";
import NoteEditForm from "@/components/NoteEditForm.vue";
import { formatDateTime, formatNoteDate } from "@/domain/format";
import type { Note } from "@/domain/note";
import { PRIORITY_LABELS, type Priority } from "@/domain/priority";
import { isLongNote } from "@/domain/wall";

/**
 * One note on the wall, as a paper sheet. A long note is clamped; clicking the sheet (or Read more)
 * unfolds it in place. With `editing` set, the sheet holds the edit form instead.
 */
const props = defineProps<{ note: Note; expanded: boolean; editing: boolean; busy?: boolean }>();
const emit = defineEmits<{
  toggle: [];
  edit: [];
  archive: [];
  restore: [];
  delete: [];
  saved: [note: Note];
  cancel: [];
  gone: [];
  dirty: [dirty: boolean];
}>();

const archived = computed(() => props.note.archivedAt !== null);
const long = computed(() => isLongNote(props.note.content));
/** While editing, the sheet takes the colour of the priority being picked. */
const editPriority = ref<Priority>(props.note.priority);
const colour = computed(() => (props.editing ? editPriority.value : props.note.priority));
const stamp = computed(() => (archived.value && props.note.archivedAt ? props.note.archivedAt : props.note.updatedAt));
const dateLabel = computed(() =>
  archived.value
    ? `Archived ${formatNoteDate(stamp.value).replace(/^(Today|Yesterday)/, (word) => word.toLowerCase())}`
    : formatNoteDate(stamp.value)
);
</script>

<template>
  <article
    class="note sheet paper"
    :class="[`sheet--${colour}`, { 'sheet--archived': archived && !editing, 'note--open': editing, 'note--long': long && !editing }]"
    :aria-label="note.title"
    :data-note-id="note.id"
  >
    <NoteEditForm
      v-if="editing"
      :note="note"
      @saved="emit('saved', $event)"
      @cancel="emit('cancel')"
      @gone="emit('gone')"
      @dirty="emit('dirty', $event)"
      @priority="editPriority = $event"
    />
    <template v-else>
      <div class="note__top">
        <span class="tag" :class="`tag--${note.priority}`"
          ><span class="dot" aria-hidden="true"></span>{{ PRIORITY_LABELS[note.priority] }}</span
        >
        <time class="gdate" :datetime="stamp" :title="formatDateTime(stamp)">{{ dateLabel }}</time>
      </div>
      <div class="note__head">
        <h2 class="note__title">
          <button v-if="long" type="button" class="note__opener" :aria-expanded="expanded ? 'true' : 'false'" @click="emit('toggle')">
            {{ note.title }}
          </button>
          <template v-else>{{ note.title }}</template>
        </h2>
        <button type="button" class="ico note__edit" aria-label="Edit" title="Edit" :disabled="busy" @click="emit('edit')">
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.9"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M4 20h4L19 9l-4-4L4 16z" />
          </svg>
        </button>
      </div>
      <p v-if="note.content" class="note__body" :class="{ 'note__body--clamp': !expanded, 'note__body--fade': long && !expanded }">
        {{ note.content }}
      </p>
      <div class="note__actions emboss">
        <button v-if="long" type="button" class="note__more" :aria-expanded="expanded ? 'true' : 'false'" @click="emit('toggle')">
          {{ expanded ? "Show less" : "Read more" }}
          <svg
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path :d="expanded ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6'" />
          </svg>
        </button>
        <button v-if="archived" type="button" class="ico" aria-label="Restore" title="Restore" :disabled="busy" @click="emit('restore')">
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.9"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <rect x="3" y="4" width="18" height="5" rx="1" />
            <path d="M5 9v10h14V9M12 17v-5M9.5 14.5 12 12l2.5 2.5" />
          </svg>
        </button>
        <button v-else type="button" class="ico" aria-label="Archive" title="Archive" :disabled="busy" @click="emit('archive')">
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.9"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <rect x="3" y="4" width="18" height="5" rx="1" />
            <path d="M5 9v10h14V9M10 13h4" />
          </svg>
        </button>
        <button type="button" class="ico ico--danger" aria-label="Delete" title="Delete" :disabled="busy" @click="emit('delete')">
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.9"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
          </svg>
        </button>
      </div>
    </template>
  </article>
</template>

<style scoped>
.note {
  transition: box-shadow 0.2s;
}
/* Hover deepens the shadow; the sheet stays where it is. */
.note:hover {
  box-shadow: var(--sheet-shadow-lifted);
}
.note--long {
  cursor: pointer;
}
.note--open,
.note--open:hover {
  padding: 20px;
  box-shadow:
    0 0 0 3px rgb(220 225 232 / 0.55),
    0 26px 40px -20px rgb(0 0 0 / 0.8);
}
.note__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.note__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 6px;
}
.note__title {
  margin: 0;
  padding-top: 3px;
  min-width: 0;
  font: 600 24px/1.15 var(--serif);
  color: var(--ink);
  overflow-wrap: anywhere;
}
.sheet--archived .note__title,
.sheet--archived .note__body {
  color: #4a4a4c;
}
/* The title button's overlay makes the whole sheet the click target for unfolding. */
.note__opener {
  all: unset;
  cursor: pointer;
}
.note__opener::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 2px;
}
.note:has(.note__opener:focus-visible) {
  outline: 2px solid var(--silver-light);
  outline-offset: 3px;
}
/* Controls stay above the title's overlay. */
.note__edit {
  position: relative;
  z-index: 1;
  margin: -2px -8px 0 0;
}
.note__body {
  margin: 0;
  font: 400 15px/1.6 var(--sans);
  color: var(--ink-body);
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.note__body--clamp {
  display: -webkit-box;
  -webkit-line-clamp: 9;
  line-clamp: 9;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.note__body--fade {
  mask-image: linear-gradient(180deg, #000 62%, transparent 100%);
}
.note__actions {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 2px;
  margin-top: 4px;
  padding-top: 8px;
}
.note__more {
  margin-right: auto;
  height: 36px;
  padding: 0 10px 0 2px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 0;
  border-radius: 2px;
  background: transparent;
  font: italic 600 16px var(--serif);
  letter-spacing: 0.02em;
  color: #4e5560;
  cursor: pointer;
}
.note__more svg {
  stroke: url(#lxGold);
}
.note__more:hover {
  color: #2f353e;
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-color: rgb(110 118 130 / 0.5);
}
</style>
