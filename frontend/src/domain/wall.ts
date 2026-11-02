/**
 * Layout of the notes wall: a masonry of equal-width columns. Notes are dealt across the columns in
 * turn, so reading order runs left to right while each column stacks tightly under its own notes.
 */

/** Narrowest a column may get, and the spacing the count allows for (values from the design). */
export const COLUMN_MIN_WIDTH = 290;
export const COLUMN_SPACING = 20;

/** How many columns fit in `width` pixels; never fewer than one. */
export function columnCount(width: number): number {
  if (!Number.isFinite(width) || width <= 0) return 1;
  return Math.max(1, Math.floor((width + COLUMN_SPACING) / (COLUMN_MIN_WIDTH + COLUMN_SPACING)));
}

/**
 * Deals `items` into `columns` columns in turn. `offset` slots are taken before the first item
 * (the quick-note composer sits in the first column), so the first note goes to column `offset`.
 */
export function dealColumns<T>(items: readonly T[], columns: number, offset = 0): T[][] {
  const count = Math.max(1, Math.floor(columns));
  const result: T[][] = Array.from({ length: count }, () => []);
  items.forEach((item, index) => result[(index + offset) % count]!.push(item));
  return result;
}

/** Lines and characters past which a note is shown clamped, with Read more (values from the design). */
export const PREVIEW_MAX_LINES = 8;
export const PREVIEW_MAX_CHARACTERS = 320;

export function isLongNote(content: string): boolean {
  return content.split("\n").length > PREVIEW_MAX_LINES || Array.from(content).length > PREVIEW_MAX_CHARACTERS;
}
