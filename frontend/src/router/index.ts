import { createRouter, createWebHistory, type RouteLocationNormalized, type RouteLocationRaw } from "vue-router";
import { useSession } from "@/composables/useSession";
import AuthView from "@/views/AuthView.vue";
import NotesWallView from "@/views/NotesWallView.vue";

declare module "vue-router" {
  interface RouteMeta {
    title?: string;
    /** Login and register: a logged-in visitor is sent to the notes instead. */
    guestOnly?: boolean;
    /** Pages anyone may see, logged in or not. Everything else needs a session. */
    public?: boolean;
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: "/", name: "notes", component: NotesWallView, props: { archived: false }, meta: { title: "Notes" } },
    { path: "/archive", name: "archive", component: NotesWallView, props: { archived: true }, meta: { title: "Archive" } },
    // Notes used to open on pages of their own; old links and bookmarks land on the wall (D017).
    { path: "/notes/:rest(.*)*", redirect: { name: "notes" } },
    { path: "/login", name: "login", component: AuthView, props: { mode: "login" }, meta: { title: "Log in", guestOnly: true } },
    {
      path: "/register",
      name: "register",
      component: AuthView,
      props: { mode: "register" },
      meta: { title: "Create account", guestOnly: true },
    },
    {
      path: "/:pathMatch(.*)*",
      name: "not-found",
      component: () => import("@/views/NotFoundView.vue"),
      meta: { title: "Not found", public: true },
    },
  ],
});

export function accessRedirect(to: Pick<RouteLocationNormalized, "meta" | "fullPath">, loggedIn: boolean): RouteLocationRaw | undefined {
  if (to.meta.guestOnly) return loggedIn ? { name: "notes" } : undefined;
  if (to.meta.public || loggedIn) return undefined;
  // Remember where the visitor was going, so logging in lands there.
  return to.fullPath === "/" ? { name: "login" } : { name: "login", query: { redirect: to.fullPath } };
}

router.beforeEach(async (to) => {
  const session = useSession();
  await session.ensureLoaded();
  return accessRedirect(to, session.user.value !== null) ?? true;
});

router.afterEach((to) => {
  document.title = `${to.meta.title ?? "Notes"} · Notes`;
});

export default router;
