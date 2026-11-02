import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import NoteForm from "../NoteForm.vue";
import type { FieldErrors, NoteInput } from "@/domain/note";

function mountForm(modelValue: NoteInput, serverErrors: FieldErrors = {}) {
  return mount(NoteForm, {
    props: {
      modelValue,
      serverErrors,
      submitLabel: "Save",
      "onUpdate:modelValue": () => undefined,
    },
    attachTo: document.body,
  });
}

describe("NoteForm", () => {
  it("does not submit an empty title and shows the error on the field", async () => {
    const wrapper = mountForm({ title: "   ", content: "", priority: "medium" });

    await wrapper.find("form").trigger("submit");

    expect(wrapper.emitted("submit")).toBeUndefined();
    expect(wrapper.find("#note-title-error").text()).toBe("Title is required.");
    expect(wrapper.find("#note-title").attributes("aria-invalid")).toBe("true");
    wrapper.unmount();
  });

  it("submits a trimmed title and the chosen priority", async () => {
    const wrapper = mountForm({ title: "  Buy milk ", content: "2 l", priority: "high" });

    await wrapper.find("form").trigger("submit");

    expect(wrapper.emitted("submit")?.[0]).toEqual([{ title: "Buy milk", content: "2 l", priority: "high" }]);
    wrapper.unmount();
  });

  it("shows server-side field errors without a submit attempt", () => {
    const wrapper = mountForm({ title: "x", content: "", priority: "low" }, { priority: "Priority must be one of: low, medium, high." });

    expect(wrapper.text()).toContain("Priority must be one of");
    wrapper.unmount();
  });

  it("disables the buttons and says so while saving", async () => {
    const wrapper = mountForm({ title: "x", content: "", priority: "low" });
    await wrapper.setProps({ submitting: true });

    const submit = wrapper.find("button[type=submit]");
    expect(submit.text()).toBe("Saving…");
    expect(submit.attributes("disabled")).toBeDefined();
    wrapper.unmount();
  });
});
