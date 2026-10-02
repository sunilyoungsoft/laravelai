---
inclusion: fileMatch
fileMatchPattern: ['**/*.tsx', '**/*.ts', '**/Resources/js/**']
---

# Frontend Architecture

Use:

React 19
TypeScript
Inertia.js
Tailwind CSS v4 (CSS-first via `@tailwindcss/vite`; no `tailwind.config.js`)
shadcn/ui for UI primitives

## Imports & tooling

- Import app code via the `@/*` alias (`@/* → resources/js/*`), already set in `tsconfig.json`
  and `vite.config.js`. Do not use long relative `../../` paths.
- shadcn/ui primitives live under `resources/js/components/ui/`; shared app components under
  `resources/js/components/`. Prefer composing shadcn primitives over hand-rolling base UI.
- Lint, format, and type-check must pass before a frontend change is considered done
  (`npm run lint`, `npm run type-check`, `npm run build`). Do not introduce `any`.
- Do not casually upgrade React, Vite, Tailwind, Inertia, or TypeScript versions; add
  dependencies deliberately.

## Inertia

Use Inertia for server-driven application pages where appropriate.

Do not duplicate backend business logic in React.

Backend remains responsible for:

- authorization
- validation
- business rules
- data integrity

## TypeScript

Prefer strong typing.

Avoid:

any

unless there is a documented reason.

## Components

Create reusable components for repeated UI patterns.

Avoid giant components.

Break complex pages into:

- components
- hooks
- utilities
- forms

## API

Do not bypass backend authorization because the UI hides a feature.

Frontend permission checks are for UX.

Backend permission checks are for security.

## Forms

Handle:

- validation errors
- loading states
- success states
- failure states

consistently.

## UI

Maintain consistent:

- spacing
- typography
- forms
- buttons
- tables
- modals
- notifications

Use shared components instead of recreating the same UI in every module.