import { describe, expect, it } from "vitest";
import { formatNoteDate } from "../format";

// Local times on purpose: the labels follow the user's own calendar day.
const now = new Date(2026, 10, 2, 15, 30);
const at = (year: number, month: number, day: number, hour = 12, minute = 0) => new Date(year, month, day, hour, minute).toISOString();

describe("formatNoteDate", () => {
  it("shows the time for today and 'Yesterday' for the day before", () => {
    expect(formatNoteDate(at(2026, 10, 2, 9, 12), now)).toBe("Today, 09:12");
    expect(formatNoteDate(at(2026, 10, 2, 0, 0), now)).toBe("Today, 00:00");
    expect(formatNoteDate(at(2026, 10, 1, 23, 59), now)).toBe("Yesterday");
  });

  it("shows day and month this year, and the year before that", () => {
    expect(formatNoteDate(at(2026, 9, 29), now)).toBe("29 Oct");
    expect(formatNoteDate(at(2025, 11, 31), now)).toBe("31 Dec 2025");
  });

  it("returns text it cannot read unchanged", () => {
    expect(formatNoteDate("not a date", now)).toBe("not a date");
  });
});
