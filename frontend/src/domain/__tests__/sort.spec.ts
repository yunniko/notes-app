import { describe, expect, it } from "vitest";
import { parseSort, serializeSort } from "../sort";

describe("sort in the URL", () => {
  it("reads known orders and falls back to newest", () => {
    expect(parseSort("oldest")).toBe("oldest");
    expect(parseSort(["priority", "oldest"])).toBe("priority");
    expect(parseSort(undefined)).toBe("newest");
    expect(parseSort("Priority")).toBe("newest");
    expect(parseSort(["x"])).toBe("newest");
  });

  it("leaves the default out of the URL", () => {
    expect(serializeSort("newest")).toBeUndefined();
    expect(serializeSort("priority")).toBe("priority");
  });
});
