<script setup lang="ts">
import { useFlash } from "@/composables/useFlash";

const flash = useFlash();
</script>

<template>
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="app-header">
    <div class="app-header__inner">
      <RouterLink class="brand" :to="{ name: 'notes' }">
        <span aria-hidden="true">🗒</span>
        Notes
      </RouterLink>
    </div>
  </header>
  <main id="main" class="app-main">
    <div class="flash-region" role="status" aria-live="polite">
      <p v-if="flash.message.value" class="flash">
        {{ flash.message.value }}
        <button type="button" class="flash__close" aria-label="Dismiss" @click="flash.dismiss">×</button>
      </p>
    </div>
    <RouterView />
  </main>
</template>

<style scoped>
.app-header {
  border-bottom: 1px solid var(--border);
  background: var(--surface);
}
.app-header__inner {
  max-width: var(--content-width);
  margin: 0 auto;
  padding: 0.9rem 1.25rem;
}
.brand {
  display: inline-flex;
  gap: 0.5rem;
  font-weight: 700;
  font-size: 1.15rem;
  color: var(--text);
  text-decoration: none;
}
.app-main {
  max-width: var(--content-width);
  margin: 0 auto;
  padding: 1.5rem 1.25rem 3rem;
}
.flash-region:empty {
  display: none;
}
.flash {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 0 0 1rem;
  padding: 0.7rem 1rem;
  border-radius: var(--radius-sm);
  background: var(--success-bg);
  color: var(--success);
  border: 1px solid var(--success);
}
.flash__close {
  border: 0;
  background: none;
  color: inherit;
  font-size: 1.25rem;
  line-height: 1;
  cursor: pointer;
}
.skip-link {
  position: absolute;
  left: -999px;
}
.skip-link:focus {
  left: 1rem;
  top: 1rem;
  z-index: 10;
  padding: 0.5rem 1rem;
  background: var(--surface);
}
</style>
