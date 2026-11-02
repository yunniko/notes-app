import { fileURLToPath, URL } from "node:url";

import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";

// `npm run dev` serves the UI on :5173 and forwards /api to the Docker stack on :8088,
// so the browser always talks to one origin (no CORS), exactly as in production.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { "@": fileURLToPath(new URL("./src", import.meta.url)) },
  },
  server: {
    proxy: { "/api": process.env.API_PROXY_TARGET ?? "http://localhost:8088" },
  },
});
