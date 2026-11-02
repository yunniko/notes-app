# D018 · The quick-note draft belongs to one account
Date: 2026-11-02 · Goal: G-004 M3 · Status: active (superseded by: —)
Context: the composer's draft lives in module state so it survives a visit to the archive, which also let it survive into the next login in the same tab.
Decision: the draft records its owner; another account starts with an empty composer, the same user returning after an expired session gets the draft back, and logging out clears it.
Force: requirement — a note's text must never be shown to another account (D010).
Rejected: clearing on every session end (loses text the user typed just before the session expired); keeping the draft in localStorage (outlives the tab, same leak).
Consequence: any new client-side state holding note text must be cleared or owner-checked on account change.
Evidence: `frontend/src/composables/__tests__/useComposer.spec.ts`; `frontend/e2e/auth.spec.ts` ("a draft is never handed to the next account")
