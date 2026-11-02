import { describe, expect, it } from "vitest";
import { emptyListMessage, parseSearch, SEARCH_MAX_LENGTH, serializeSearch } from "@/domain/search";

describe("parseSearch", () => {
  it("trims, takes the first of repeated values, and ignores non-text", () => {
    expect(parseSearch("  budget ")).toBe("budget");
    expect(parseSearch(["first", "second"])).toBe("first");
    expect(parseSearch(undefined)).toBe("");
    expect(parseSearch(null)).toBe("");
  });

  it("cuts a hand-edited query to the API limit", () => {
    expect(parseSearch("a".repeat(SEARCH_MAX_LENGTH + 20))).toHaveLength(SEARCH_MAX_LENGTH);
  });
});

describe("serializeSearch", () => {
  it("removes a blank search from the URL", () => {
    expect(serializeSearch("   ")).toBeUndefined();
    expect(serializeSearch(" meeting notes ")).toBe("meeting notes");
  });
});

describe("emptyListMessage", () => {
  it("names what narrowed the list", () => {
    expect(emptyListMessage({ archived: false, search: "tax", filtered: false })).toBe("No notes match “tax”.");
    expect(emptyListMessage({ archived: true, search: "tax", filtered: true })).toBe("No archived notes with this priority match “tax”.");
    expect(emptyListMessage({ archived: false, search: "", filtered: true })).toBe("No notes with this priority.");
  });

  it("returns null when nothing narrows the list", () => {
    expect(emptyListMessage({ archived: false, search: "", filtered: false })).toBeNull();
  });
});
