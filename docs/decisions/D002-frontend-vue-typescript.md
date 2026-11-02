# D002 · Frontend: Vue 3 + TypeScript + Vite, no component library
Date: 2026-11-02 · Goal: G-001 M3 · Status: active (superseded by: —)
Context: the fullstack variant asks for a UI in a modern framework, for example Vue.
Decision: Vue 3 (Composition API, `<script setup>`) with TypeScript, Vite and vue-router, hand-written CSS, and no state library or UI kit.
Force: judgment — the brief names Vue as its example, and Vue with TypeScript has first-class Vitest and Playwright tooling.
Rejected: React (equally valid, but the brief suggests Vue); Pinia (the only shared state is a single flash message); Vuetify/PrimeVue (a large dependency for three screens that would hide the accessibility and state work).
Consequence: business rules the UI repeats (limits, filter parsing) stay in pure modules under `frontend/src/domain/`, so they are unit-tested without mounting components.
Evidence: `frontend/package.json`; `frontend/src/domain/__tests__/note.spec.ts`
