import { createRouter, createWebHistory, type RouteLocationNormalized, type RouteLocationRaw } from "vue-router";
import { useSession } from "@/composables/useSession";
import AuthView from "@/views/AuthView.vue";
import NotesListView from "@/views/NotesListView.vue";

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
    { path: "/", name: "notes", component: NotesListView, props: { archived: false }, meta: { title: "Notes" } },
    { path: "/archive", name: "archive", component: NotesListView, props: { archived: true }, meta: { title: "Archive" } },
    { path: "/notes/new", name: "note-new", component: () => import("@/views/NoteEditorView.vue"), meta: { title: "New note" } },
    {
      // Only positive integers match; anything else falls through to the not-found page.
      path: "/notes/:id(\\d+)",
      name: "note-edit",
      component: () => import("@/views/NoteEditorView.vue"),
      props: (route) => ({ id: Number(route.params.id) }),
      meta: { title: "Edit note" },
    },
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
