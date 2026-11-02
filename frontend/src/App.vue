<script setup lang="ts">
import { watch } from "vue";
import AppHeader from "@/components/AppHeader.vue";
import { useComposer } from "@/composables/useComposer";
import { useFlash } from "@/composables/useFlash";
import { useSession } from "@/composables/useSession";

const flash = useFlash();
const session = useSession();
const composer = useComposer();
watch(() => session.user.value?.id ?? null, composer.belongTo, { immediate: true });
</script>

<template>
  <div class="board">
    <!-- The silver gradient every icon stroke on paper refers to (`stroke: url(#lxGold)`). -->
    <svg class="defs" width="0" height="0" aria-hidden="true" focusable="false">
      <defs>
        <linearGradient id="lxGold" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#8E96A3" />
          <stop offset="0.45" stop-color="#4E5560" />
          <stop offset="0.7" stop-color="#7D8592" />
          <stop offset="1" stop-color="#3F4650" />
        </linearGradient>
      </defs>
    </svg>
    <a class="skip-link" href="#main">Skip to content</a>
    <AppHeader />
    <main id="main" class="app-main">
      <div class="flash-region" role="status" aria-live="polite">
        <p v-if="flash.message.value" class="flash paper">
          {{ flash.message.value }}
          <button type="button" class="flash__close" aria-label="Dismiss" @click="flash.dismiss">×</button>
        </p>
      </div>
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.defs {
  position: absolute;
}
.app-main {
  max-width: var(--content-width);
  margin: 0 auto;
  padding: 28px 32px 72px;
}
@media (max-width: 640px) {
  .app-main {
    padding: 20px 16px 56px;
  }
}
.flash-region:empty {
  display: none;
}
.flash {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  max-width: 40rem;
  margin: 0 auto 24px;
  padding: 10px 10px 10px 18px;
  background-color: var(--paper-menu);
  color: var(--ink);
  border: 1px solid rgb(174 182 194 / 0.6);
  border-radius: 2px;
  box-shadow: 0 12px 30px -14px rgb(0 0 0 / 0.7);
  font: italic 500 18px var(--serif);
}
.flash__close {
  flex: none;
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 2px;
  background: none;
  color: var(--ink-muted);
  font-size: 1.4rem;
  line-height: 1;
  cursor: pointer;
}
.flash__close:hover {
  background: rgb(110 118 130 / 0.12);
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
  background: var(--cream);
  color: var(--navy);
}
</style>
