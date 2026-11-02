const dateTimeFormat = new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" });
const timeFormat = new Intl.DateTimeFormat("en-GB", { hour: "2-digit", minute: "2-digit" });
const dayMonthFormat = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short" });
const dayMonthYearFormat = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric" });

/** An API timestamp (UTC, ISO 8601) in the user's own locale and time zone. */
export function formatDateTime(iso: string): string {
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : dateTimeFormat.format(date);
}

/**
 * The short date on a note, in the user's time zone: "Today, 09:12", "Yesterday", "29 Oct", or
 * "29 Oct 2025" outside the current year. The full date and time stay available as a tooltip.
 */
export function formatNoteDate(iso: string, now: Date = new Date()): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  const day = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
  const daysAgo = Math.round((day(now) - day(date)) / 86_400_000);
  if (daysAgo === 0) return `Today, ${timeFormat.format(date)}`;
  if (daysAgo === 1) return "Yesterday";
  return (date.getFullYear() === now.getFullYear() ? dayMonthFormat : dayMonthYearFormat).format(date);
}
