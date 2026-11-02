import { describe, expect, it } from "vitest";
import { CONTENT_MAX_LENGTH, TITLE_MAX_LENGTH, characterCount, validateNoteInput } from "../note";

describe("validateNoteInput", () => {
  it("accepts a title-only note", () => {
    expect(validateNoteInput({ title: "Call mum", content: "", priority: "low" })).toEqual({});
  });

  it("requires a title that is not just whitespace", () => {
    expect(validateNoteInput({ title: "  \n ", content: "", priority: "low" })).toEqual({ title: "Title is required." });
  });

  it("limits the trimmed title length", () => {
    expect(validateNoteInput({ title: ` ${"a".repeat(TITLE_MAX_LENGTH)} `, content: "", priority: "low" })).toEqual({});
    expect(validateNoteInput({ title: "a".repeat(TITLE_MAX_LENGTH + 1), content: "", priority: "low" }).title).toMatch(/at most 200/);
  });

  it("limits content length", () => {
    expect(validateNoteInput({ title: "x", content: "a".repeat(CONTENT_MAX_LENGTH + 1), priority: "low" }).content).toMatch(
      /at most 20000/
    );
  });
});

describe("characterCount", () => {
  it("counts code points like the backend's mb_strlen, not UTF-16 units", () => {
    expect(characterCount("řeč")).toBe(3);
    expect(characterCount("🐎")).toBe(1); // "🐎".length === 2
  });
});
