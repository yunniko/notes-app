import { describe, expect, it } from "vitest";
import { isPriority, parsePriorityFilter, serializePriorityFilter } from "../priority";

describe("parsePriorityFilter", () => {
  it("reads a comma-separated query value in display order without duplicates", () => {
    expect(parsePriorityFilter("low,high,low")).toEqual(["high", "low"]);
  });

  it("treats a missing or empty value as no filter", () => {
    expect(parsePriorityFilter(undefined)).toEqual([]);
    expect(parsePriorityFilter("")).toEqual([]);
  });

  it("drops unknown values instead of failing, so stale links still open", () => {
    expect(parsePriorityFilter("urgent, medium")).toEqual(["medium"]);
  });

  it("accepts the repeated-parameter form ?priority=a&priority=b", () => {
    expect(parsePriorityFilter(["low", "medium"])).toEqual(["medium", "low"]);
  });
});

describe("serializePriorityFilter", () => {
  it("removes the parameter when nothing or everything is selected", () => {
    expect(serializePriorityFilter([])).toBeUndefined();
    expect(serializePriorityFilter(["low", "high", "medium"])).toBeUndefined();
  });

  it("writes a stable order regardless of click order", () => {
    expect(serializePriorityFilter(["low", "high"])).toBe("high,low");
  });

  it("round-trips through parsePriorityFilter", () => {
    expect(parsePriorityFilter(serializePriorityFilter(["medium", "low"]))).toEqual(["medium", "low"]);
  });
});

it("isPriority narrows only the three known values", () => {
  expect(["high", "medium", "low", "HIGH", "", null].map(isPriority)).toEqual([true, true, true, false, false, false]);
});
