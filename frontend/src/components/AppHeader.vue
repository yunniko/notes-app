<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AccountMenu from "@/components/AccountMenu.vue";
import SearchBox from "@/components/SearchBox.vue";
import { useFlash } from "@/composables/useFlash";
import { useSession } from "@/composables/useSession";
import { parseSearch, serializeSearch } from "@/domain/search";

/**
 * The sticky header: logo, the search of the list being shown, New note and the account menu.
 * The search lives in the URL (`?q=`), so the header and the list read the same value.
 */
const route = useRoute();
const router = useRouter();
const flash = useFlash();
const session = useSession();
const loggingOut = ref(false);

const onList = computed(() => route.name === "notes" || route.name === "archive");
const search = computed<string>({
  get: () => parseSearch(route.query.q),
  set: (text) => router.replace({ query: { ...route.query, q: serializeSearch(text) } }),
});

async function logOut(): Promise<void> {
  loggingOut.value = true;
  try {
    await session.logOut();
  } catch {
    // The server may be unreachable; the local session is cleared either way.
  } finally {
    loggingOut.value = false;
  }
  await router.push({ name: "login" });
  flash.show("You are logged out.");
}
</script>

<template>
  <header class="head">
    <div class="head__inner">
      <RouterLink class="logo" :to="{ name: 'notes' }">
        <span class="logo__mark" aria-hidden="true">N</span>
        <span class="logo__name">Notes</span>
      </RouterLink>
      <SearchBox v-if="session.user.value && onList" v-model="search" class="head__search" />
      <div v-if="session.user.value" class="head__end">
        <RouterLink class="goldbtn" :to="{ name: 'note-new' }">
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
            aria-hidden="true"
          >
            <path d="M12 5v14M5 12h14" />
          </svg>
          New note
        </RouterLink>
        <AccountMenu :username="session.user.value.username" :busy="loggingOut" @log-out="logOut" />
      </div>
    </div>
  </header>
</template>

<style scoped>
.head {
  position: sticky;
  top: 0;
  z-index: 2;
  background: rgb(8 15 29 / 0.94);
  backdrop-filter: blur(6px);
  border-bottom: 1px solid rgb(174 182 194 / 0.6);
  box-shadow: 0 4px 0 -3px rgb(174 182 194 / 0.3);
}
.head__inner {
  max-width: var(--content-width);
  margin: 0 auto;
  padding: 14px 32px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 28px;
}
.logo {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  color: var(--cream);
  text-decoration: none;
}
.logo:hover {
  color: var(--cream);
}
.logo__mark {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  border: 1px solid var(--silver);
  box-shadow:
    inset 0 0 0 3px var(--board),
    inset 0 0 0 4px rgb(174 182 194 / 0.55);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font: italic 600 20px var(--serif);
  color: var(--silver-light);
}
.logo__name {
  font: 600 26px/1 var(--serif);
  letter-spacing: 0.04em;
}
.head__search {
  flex: 1 1 360px;
  max-width: 600px;
}
.head__end {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 12px;
}
.goldbtn {
  text-decoration: none;
}
@media (max-width: 640px) {
  .head__inner {
    padding: 12px 16px;
  }
  /* The search takes its own row under the logo and buttons. */
  .head__search {
    order: 3;
    flex-basis: 100%;
  }
}
</style>
