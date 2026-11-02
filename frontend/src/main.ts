import { createApp } from "vue";
import App from "./App.vue";
import { setUnauthorizedHandler } from "./api/http";
import { useFlash } from "./composables/useFlash";
import { useSession } from "./composables/useSession";
import router from "./router";
import "./assets/main.css";

// A 401 from a notes request means the session ended while the page was open.
setUnauthorizedHandler(() => {
  useSession().expired();
  useFlash().show("Your session has ended. Please log in again.", 8000);
  const { fullPath } = router.currentRoute.value;
  void router.push({ name: "login", query: fullPath === "/" ? {} : { redirect: fullPath } });
});

createApp(App).use(router).mount("#app");
