import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import ConfirmDialog from "../ConfirmDialog.vue";

function mountDialog(open: boolean, busy = false) {
  return mount(ConfirmDialog, {
    props: { open, busy, title: "Delete this note?", confirmLabel: "Delete permanently" },
    slots: { default: "<p>Gone for good.</p>" },
    attachTo: document.body,
  });
}

describe("ConfirmDialog", () => {
  it("opens and closes with the open prop", async () => {
    const wrapper = mountDialog(false);
    const dialog = wrapper.find("dialog").element as HTMLDialogElement;
    expect(dialog.open).toBe(false);

    await wrapper.setProps({ open: true });
    expect(dialog.open).toBe(true);
    expect(wrapper.text()).toContain("Gone for good.");

    await wrapper.setProps({ open: false });
    expect(dialog.open).toBe(false);
    wrapper.unmount();
  });

  it("asks the parent to confirm or cancel", async () => {
    const wrapper = mountDialog(true);

    await wrapper.get("button.button--danger").trigger("click");
    await wrapper.findAll("button")[0]!.trigger("click");
    await wrapper.find("dialog").trigger("cancel"); // Escape

    expect(wrapper.emitted("confirm")).toHaveLength(1);
    expect(wrapper.emitted("cancel")).toHaveLength(2);
    wrapper.unmount();
  });

  it("cannot be dismissed while the action runs", async () => {
    const wrapper = mountDialog(true, true);

    await wrapper.find("dialog").trigger("cancel");

    expect(wrapper.emitted("cancel")).toBeUndefined();
    expect(wrapper.get("button.button--danger").text()).toBe("Working…");
    wrapper.unmount();
  });
});
