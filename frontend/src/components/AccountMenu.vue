<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from "vue";

/**
 * The round account button with a small menu under it: who is logged in, and Log out.
 * Closes on Escape (focus returns to the button), on a click outside, and after choosing.
 */
const props = defineProps<{ username: string; busy?: boolean }>();
const emit = defineEmits<{ logOut: [] }>();

const open = ref(false);
const root = useTemplateRef<HTMLDivElement>("root");
const toggle = useTemplateRef<HTMLButtonElement>("toggle");
const initial = computed(() => props.username.charAt(0).toUpperCase());

function close(returnFocus: boolean): void {
  open.value = false;
  if (returnFocus) toggle.value?.focus();
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === "Escape" && open.value) {
    event.preventDefault();
    close(true);
  }
}

function onDocumentClick(event: MouseEvent): void {
  if (open.value && root.value && !root.value.contains(event.target as Node)) close(false);
}

function logOut(): void {
  close(false);
  emit("logOut");
}

onMounted(() => document.addEventListener("click", onDocumentClick));
onBeforeUnmount(() => document.removeEventListener("click", onDocumentClick));
</script>

<template>
  <div ref="root" class="account" @keydown="onKeydown">
    <button
      ref="toggle"
      type="button"
      class="account__toggle"
      :aria-label="`Account: ${username}`"
      :aria-expanded="open ? 'true' : 'false'"
      aria-controls="account-menu"
      @click="open = !open"
    >
      {{ initial }}
    </button>
    <div v-if="open" id="account-menu" class="account__menu paper">
      <p class="account__who">
        Logged in as <strong>{{ username }}</strong>
      </p>
      <div class="account__divider"></div>
      <button type="button" class="menuitem" :disabled="busy" @click="logOut">
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
          <path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10" />
        </svg>
        Log out
      </button>
    </div>
  </div>
</template>

<style scoped>
.account {
  position: relative;
}
.account__toggle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1px solid var(--silver);
  box-shadow:
    inset 0 0 0 3px var(--board),
    inset 0 0 0 4px rgb(174 182 194 / 0.45);
  background: var(--navy-deep);
  color: var(--cream);
  font: italic 600 19px var(--serif);
  cursor: pointer;
}
.account__menu {
  position: absolute;
  top: 54px;
  right: 0;
  z-index: 5;
  min-width: 230px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  background-color: var(--paper-menu);
  border: 1px solid rgb(174 182 194 / 0.6);
  border-radius: 2px;
  box-shadow: 0 20px 40px -14px rgb(0 0 0 / 0.75);
}
.account__who {
  margin: 0;
  padding: 8px 12px;
  font: italic 500 16px var(--serif);
  color: var(--ink-soft);
  overflow-wrap: anywhere;
}
.account__who strong {
  font-style: normal;
  font-weight: 700;
  color: var(--ink);
}
.account__divider {
  height: 1px;
  margin: 2px 6px 6px;
  background: rgb(140 148 160 / 0.45);
}
</style>
