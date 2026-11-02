import { describe, expect, it } from "vitest";
import { columnCount, dealColumns, isLongNote } from "../wall";

describe("columnCount", () => {
  it("fits as many 290px columns as the width allows, at least one", () => {
    expect(columnCount(0)).toBe(1);
    expect(columnCount(Number.NaN)).toBe(1);
    expect(columnCount(599)).toBe(1);
    expect(columnCount(600)).toBe(2); // 2 × 290 + one 20px space
    expect(columnCount(1296)).toBe(4); // the design's 1360px page minus its padding
  });
});

describe("dealColumns", () => {
  it("deals in turn, so reading order runs left to right", () => {
    expect(dealColumns([1, 2, 3, 4, 5], 3)).toEqual([[1, 4], [2, 5], [3]]);
  });

  it("skips the slots taken before the first note", () => {
    expect(dealColumns([1, 2, 3, 4], 3, 1)).toEqual([[3], [1, 4], [2]]);
  });

  it("puts everything in one column when only one fits, and keeps empty columns", () => {
    expect(dealColumns(["a", "b"], 1, 1)).toEqual([["a", "b"]]);
    expect(dealColumns([], 2)).toEqual([[], []]);
    expect(dealColumns(["a"], 0)).toEqual([["a"]]);
  });
});

describe("isLongNote", () => {
  it("is long past 8 lines or 320 characters", () => {
    expect(isLongNote("")).toBe(false);
    expect(isLongNote(Array(8).fill("x").join("\n"))).toBe(false);
    expect(isLongNote(Array(9).fill("x").join("\n"))).toBe(true);
    expect(isLongNote("a".repeat(320))).toBe(false);
    expect(isLongNote("a".repeat(321))).toBe(true);
    expect(isLongNote("ř".repeat(320))).toBe(false); // characters, not bytes
  });
});
