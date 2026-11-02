import { beforeEach, describe, expect, it } from "vitest";
import { resetComposerForTests, useComposer } from "../useComposer";

describe("useComposer", () => {
  beforeEach(() => resetComposerForTests());

  it("keeps the draft when the same user comes back, clears it for another account", () => {
    const composer = useComposer();
    composer.belongTo(1);
    composer.draft.value = { title: "Half written", content: "", priority: "high" };
    composer.close();

    composer.belongTo(null); // session ended
    composer.belongTo(1);
    expect(composer.draft.value.title).toBe("Half written");
    expect(composer.closed.value).toBe(true);

    composer.belongTo(null);
    composer.belongTo(2);
    expect(composer.draft.value).toEqual({ title: "", content: "", priority: "medium" });
    expect(composer.hasDraft.value).toBe(false);
    expect(composer.closed.value).toBe(false);
  });

  it("New note shows unless the composer is both on the wall and in view", () => {
    const composer = useComposer();
    expect(composer.newNoteVisible.value).toBe(true);
    composer.report({ shown: true, inView: true });
    expect(composer.newNoteVisible.value).toBe(false);
    composer.report({ inView: false });
    expect(composer.newNoteVisible.value).toBe(true);
  });

  it("reveal reopens a closed composer and stays pending until the wall handles it", () => {
    const composer = useComposer();
    composer.close();
    composer.reveal();
    expect(composer.closed.value).toBe(false);
    expect(composer.revealPending.value).toBe(true);
    composer.revealHandled();
    expect(composer.revealPending.value).toBe(false);
  });
});
