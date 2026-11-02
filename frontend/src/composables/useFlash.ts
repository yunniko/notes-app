import { readonly, ref } from "vue";

const message = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

/**
 * A one-line confirmation shown after navigation ("Note saved."). Module-level state, so the
 * editor can set it and the list view, mounted after the route change, can show it.
 */
export function useFlash() {
  function show(text: string, durationMs = 4000): void {
    message.value = text;
    clearTimeout(timer);
    timer = setTimeout(() => (message.value = null), durationMs);
  }

  function dismiss(): void {
    clearTimeout(timer);
    message.value = null;
  }

  return { message: readonly(message), show, dismiss };
}
