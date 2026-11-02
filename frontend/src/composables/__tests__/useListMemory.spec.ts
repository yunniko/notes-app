import { beforeEach, describe, expect, it } from "vitest";
import { resetListMemoryForTests, useListMemory } from "@/composables/useListMemory";

describe("useListMemory", () => {
  beforeEach(resetListMemoryForTests);

  it("falls back to the plain list before any list was seen", () => {
    expect(useListMemory().backTo("notes")).toEqual({ name: "notes" });
    expect(useListMemory().backTo("archive")).toEqual({ name: "archive" });
  });

  it("returns to the last URL of each list separately", () => {
    const lists = useListMemory();
    lists.remember("notes", "/?priority=high&q=budget");
    lists.remember("archive", "/archive?q=old");

    expect(useListMemory().backTo("notes")).toBe("/?priority=high&q=budget");
    expect(useListMemory().backTo("archive")).toBe("/archive?q=old");
  });
});
