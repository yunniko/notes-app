import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import SearchBox from "../SearchBox.vue";

function mountBox(modelValue = "") {
  return mount(SearchBox, { props: { modelValue, debounceMs: 300 } });
}

describe("SearchBox", () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it("is a labelled search field", () => {
    const wrapper = mountBox();

    expect(wrapper.find("form").attributes("role")).toBe("search");
    expect(wrapper.find("label").text()).toBe("Search notes");
    expect(wrapper.find("input").attributes("maxlength")).toBe("100");
  });

  it("commits once typing pauses, trimmed", async () => {
    const wrapper = mountBox();
    const input = wrapper.find("input");

    await input.setValue("bud");
    vi.advanceTimersByTime(200);
    await input.setValue("budget ");
    vi.advanceTimersByTime(299);
    expect(wrapper.emitted("update:modelValue")).toBeUndefined();

    vi.advanceTimersByTime(1);
    expect(wrapper.emitted("update:modelValue")).toEqual([["budget"]]);
  });

  it("commits at once on Enter, and clears on Escape or the clear button", async () => {
    const wrapper = mountBox("old");
    const input = wrapper.find("input");

    await input.setValue("new");
    await wrapper.find("form").trigger("submit");
    expect(wrapper.emitted("update:modelValue")?.at(-1)).toEqual(["new"]);

    await wrapper.setProps({ modelValue: "new" });
    await input.trigger("keydown", { key: "Escape" });
    expect(wrapper.emitted("update:modelValue")?.at(-1)).toEqual([""]);
    expect(input.element.value).toBe("");

    await wrapper.setProps({ modelValue: "" });
    await input.setValue("again");
    await wrapper.get("button[aria-label='Clear search text']").trigger("click");
    expect(wrapper.emitted("update:modelValue")?.at(-1)).toEqual([""]);
  });

  it("shows a search applied from outside, such as the Back button", async () => {
    const wrapper = mountBox("first");

    await wrapper.setProps({ modelValue: "second" });

    expect(wrapper.find("input").element.value).toBe("second");
  });
});
