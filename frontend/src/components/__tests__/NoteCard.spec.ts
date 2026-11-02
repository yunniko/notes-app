import { describe, expect, it } from "vitest";
import { RouterLinkStub, mount } from "@vue/test-utils";
import NoteCard from "../NoteCard.vue";
import type { Note } from "@/domain/note";

const note: Note = {
  id: 7,
  title: "Dentist",
  content: "Tue 9:00",
  priority: "high",
  version: 1,
  createdAt: "2026-11-02T08:00:00+00:00",
  updatedAt: "2026-11-02T09:00:00+00:00",
  archivedAt: null,
};

function mountCard(props: { note: Note; busy?: boolean }) {
  return mount(NoteCard, { props, global: { stubs: { RouterLink: RouterLinkStub } } });
}

describe("NoteCard", () => {
  it("offers Archive and Delete for an active note", async () => {
    const wrapper = mountCard({ note });

    expect(wrapper.findAll("button").map((b) => b.text())).toEqual(["Archive", "Delete"]);
    expect(wrapper.text()).toContain("Updated");
    await wrapper.findAll("button")[0]!.trigger("click");
    await wrapper.findAll("button")[1]!.trigger("click");
    expect(Object.keys(wrapper.emitted())).toEqual(expect.arrayContaining(["archive", "delete"]));
  });

  it("offers Restore for an archived note and says when it was archived", async () => {
    const wrapper = mountCard({ note: { ...note, archivedAt: "2026-11-02T10:00:00+00:00" } });

    expect(wrapper.findAll("button").map((b) => b.text())).toEqual(["Restore", "Delete"]);
    expect(wrapper.find("time").attributes("datetime")).toBe("2026-11-02T10:00:00+00:00");
    expect(wrapper.text()).toContain("Archived");
    await wrapper.findAll("button")[0]!.trigger("click");
    expect(wrapper.emitted("restore")).toHaveLength(1);
  });

  it("disables its actions while one is running", () => {
    const wrapper = mountCard({ note, busy: true });

    expect(wrapper.findAll("button").every((b) => b.attributes("disabled") !== undefined)).toBe(true);
  });
});
