# D007 · The priority filter is a multi-select stored in the URL
Date: 2026-11-02 · Goal: G-001 M3 · Status: active (superseded by: —)
Context: the brief asks to list notes filtered by priority. A single-value filter cannot show "high and medium", and filter state kept in memory is lost on reload.
Decision: the API takes `?priority=high,medium` (deduplicated, validated, where empty means all), and the UI mirrors it in the page URL using `router.replace`.
Force: judgment — the URL makes a filtered view shareable, reloadable and back-button safe, at almost no cost.
Rejected: one priority at a time (too narrow); repeated `priority[]=` parameters (PHP-specific syntax and awkward for other clients); client-side filtering of the full list (it would not scale once pagination exists).
Consequence: an unknown priority is a 422, not silently ignored, and the UI parses the URL tolerantly, dropping unknown values.
Evidence: `frontend/src/domain/priority.ts`; `frontend/e2e/notes.spec.ts` ("chips filter the list")
