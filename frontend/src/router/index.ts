import { createRouter, createWebHistory } from "vue-router";
import NotesListView from "@/views/NotesListView.vue";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: "/", name: "notes", component: NotesListView, meta: { title: "Notes" } },
    { path: "/notes/new", name: "note-new", component: () => import("@/views/NoteEditorView.vue"), meta: { title: "New note" } },
    {
      // Only positive integers match; anything else falls through to the not-found page.
      path: "/notes/:id(\\d+)",
      name: "note-edit",
      component: () => import("@/views/NoteEditorView.vue"),
      props: (route) => ({ id: Number(route.params.id) }),
      meta: { title: "Edit note" },
    },
    { path: "/:pathMatch(.*)*", name: "not-found", component: () => import("@/views/NotFoundView.vue"), meta: { title: "Not found" } },
  ],
});

router.afterEach((to) => {
  document.title = `${typeof to.meta.title === "string" ? to.meta.title : "Notes"} · Notes`;
});

export default router;
