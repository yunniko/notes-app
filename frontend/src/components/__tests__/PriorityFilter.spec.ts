import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import PriorityFilter from "../PriorityFilter.vue";
import type { Priority } from "@/domain/priority";

function mountFilter(modelValue: Priority[]) {
  return mount(PriorityFilter, { props: { modelValue } });
}

describe("PriorityFilter", () => {
  it("marks All as pressed when nothing is selected", () => {
    const buttons = mountFilter([]).findAll("button");

    expect(buttons.map((b) => b.text())).toEqual(["All", "High", "Medium", "Low"]);
    expect(buttons.map((b) => b.attributes("aria-pressed"))).toEqual(["true", "false", "false", "false"]);
  });

  it("adds and removes a priority when its chip is toggled", async () => {
    const wrapper = mountFilter(["high"]);

    await wrapper.findAll("button")[3]!.trigger("click"); // Low
    expect(wrapper.emitted("update:modelValue")?.at(-1)).toEqual([["high", "low"]]);

    await wrapper.setProps({ modelValue: ["high", "low"] });
    await wrapper.findAll("button")[1]!.trigger("click"); // High
    expect(wrapper.emitted("update:modelValue")?.at(-1)).toEqual([["low"]]);
  });

  it("clears the selection with All", async () => {
    const wrapper = mountFilter(["medium"]);

    await wrapper.findAll("button")[0]!.trigger("click");
    expect(wrapper.emitted("update:modelValue")?.at(-1)).toEqual([[]]);
  });
});
