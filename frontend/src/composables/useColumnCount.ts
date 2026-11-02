import { onBeforeUnmount, ref, watch, type Ref } from "vue";
import { columnCount } from "@/domain/wall";

/** The number of wall columns that fit the element's width, kept up to date as it resizes. */
export function useColumnCount(element: Readonly<Ref<HTMLElement | null>>) {
  const columns = ref(1);
  let observer: ResizeObserver | undefined;

  const measure = (el: HTMLElement) => {
    columns.value = columnCount(el.clientWidth);
  };

  watch(
    element,
    (el) => {
      observer?.disconnect();
      observer = undefined;
      if (!el) return;
      measure(el);
      if (typeof ResizeObserver !== "undefined") {
        observer = new ResizeObserver(() => measure(el));
        observer.observe(el);
      }
    },
    { immediate: true }
  );

  onBeforeUnmount(() => observer?.disconnect());
  return columns;
}
