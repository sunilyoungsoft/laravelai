---
inclusion: fileMatch
fileMatchPattern: ['**/*.tsx', '**/*.ts', '**/Resources/js/**']
---

# Frontend Architecture

Use:

React
TypeScript
Inertia.js
Tailwind CSS

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