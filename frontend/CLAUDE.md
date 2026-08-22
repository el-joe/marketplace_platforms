# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md

# Project structure

## Feature folders

Every feature lives under `src/features/<domain>/.../<feature-name>/` (e.g.
`src/features/noon/profile/profile/`). We maximize React Server Components
(RSCs): minimal client bundles, data fetched as close to the source as
possible, and interactivity pushed down to the smallest leaf nodes that
actually need it.

Reference implementation: `src/features/noon/profile/profile/`

```
profile/
├── index.tsx                       # RSC entry orchestrator — fetches data, composes children
├── components/                      # Sub-components. RSC by default; "use client" only on
│   │                                 # the specific leaf that needs interactivity/hooks.
│   ├── profile-form.tsx              # "use client" — the whole section is interactive (react-hook-form)
│   ├── delete-account-dialog.tsx      # RSC — fetches its own translations, passes them down
│   │                                    # as plain string props (never a function)
│   └── delete-account-trigger.tsx     # "use client" leaf — calls use-profile-actions, renders ConfirmDialog
├── helpers/                         # Business logic, types, schema boundary
│   ├── profile.schema.ts             # zod schema + form value types
│   ├── constants.ts                   # option arrays (e.g. nationalities)
│   ├── to-form-values.ts              # pure data-shaping helpers (API shape -> form shape)
│   ├── use-form-actions.ts            # "use client" — the update-profile FORM only
│   │                                    # (react-hook-form instance, isSaving, onSubmit)
│   └── use-profile-actions.ts         # "use client" — every other feature-level action
│                                        # (deleteAccount today; deactivateAccount etc. later)
└── api/                             # Feature-only data actions, not shared elsewhere
    └── profile.actions.ts            # e.g. deleteProfile — destructive/one-off, not reused
```

**File names are always kebab-case** (`use-form-actions.ts`, not
`useFormActions.ts`), matching every other file in the codebase
(`profile-form.tsx`, `to-form-values.ts`, `case-list-view.tsx`, ...) — hook
files are no exception, even though the exported hook itself is still
camelCase (`export function useFormActions(...)`) because that's a React
requirement, not a file-naming one.

Components stay pure UI: they call a hook from `helpers/` and render what it
returns (`form`, `isSaving`, `onSubmit`, `deleteAccount`, ...). A component
file should never itself hold `useState`/mutation-style async logic — that
always lives in `helpers/`, and **form actions and feature actions are
always split into separate hook files**: the form's own state/submit lives
in `use-form-actions.ts`; everything else (delete account, deactivate
account, and any other non-form action added later) lives in
`use-profile-actions.ts`. Don't fold a new destructive/side-effect action
into `use-form-actions.ts` just because it's on the same page.

Data that's genuinely shared across features (e.g. `getProfile`/
`updateProfile` — usable from anywhere a profile needs reading or editing)
lives outside any single feature, in `src/services/<name>.ts` — see
`src/services/profile.ts`. `api/` inside a feature folder is only for
actions specific to that one screen (a destructive one-off like account
deletion, not a general-purpose read/write).

### Core RSC rules

1. **The Downward Client Boundary Rule.** Any component directly *imported*
   into a `"use client"` file becomes part of that client bundle, even if the
   component itself carries no directive of its own. There's no such thing
   as "a server component nested inside a client component's import tree."
2. **The Children Hole Punch Pattern.** To keep a subtree server-rendered
   while still wrapping an interactive piece in client behavior, don't move
   the whole section behind `"use client"` — keep the *outer* component an
   RSC and pass the interactive piece down as `children` (or a named slot
   prop). `delete-account-dialog.tsx` stays an RSC (fetches its own
   translations via `getTranslations`) and renders
   `<DeleteAccountTrigger triggerText={...} title={...} .../>` — only the
   translated *strings* cross the boundary as props, and the trigger itself
   (the only actual client leaf) turns them into the confirm dialog. Only
   introduce an intermediate RSC wrapper when there's real work for it to do
   server-side (fetching data/translations); don't add an empty pass-through
   wrapper around a leaf that has nothing of its own to fetch or render.
3. **The Data Flow Boundary.** Client state, event handlers, or hooks can
   never be passed as props into (or called from) a Server Component —
   `index.tsx` and `delete-account-dialog.tsx` are `async` Server Components
   and literally cannot call `use-profile-actions.ts`'s hook themselves, the
   same way they can't call any other React hook. The hook call has to live
   in the client leaf that needs the resulting function
   (`delete-account-trigger.tsx` calls `useProfileActions()` and passes
   `deleteAccount` straight into `<ConfirmDialog onConfirm={deleteAccount} />`,
   never up through a server parent). If a client leaf needs shared
   state/actions (e.g. logging out after deleting the account), it reads
   them from a Context provider inside itself (see `useAuthContext()` in
   `use-profile-actions.ts`), not from props handed down through a server
   parent. The one exception is Server Actions (`"use server"` functions),
   which — unlike plain functions — *can* be created in a Server Component
   and passed down; this codebase doesn't use them for feature actions yet
   (see the api.ts/services convention above), so treat "pass a function
   from index.tsx as a prop" as invalid until that changes.

Rules of thumb:
- `index.tsx` is always the entry point / orchestrator for its folder. It
  composes child components and holds minimal-to-no logic of its own.
- Constant arrays / option lists / enums used for filters, selects, or
  forms go in `helpers/constants.ts` — never inlined at the top of a
  component file.
- Zod schemas + form value types go in `helpers/<thing>.schema.ts`. Pure
  data-shaping functions (API shape -> form shape, etc.) go in `helpers/`,
  not inline inside a component file.

> Older features (`orders/`, `support/`) predate this convention and use a
> flatter `types.ts`/`data.ts`/`api.ts`/`case-status.ts` layout at the
> feature root instead of `components/`/`helpers/`. Follow `profile/` for
> new work — don't copy the older shape — but don't churn-refactor
> `orders/`/`support/` into the new layout just to rename folders unless
> you're already touching that feature for another reason.

### Gotcha: splitting a file can silently break the client/server boundary

If a component was working as a single `"use client"` file and you split it
into `index.tsx` (mode switch) + `case-list-view.tsx` (state) + `filter.tsx`
+ `table.tsx`, the **new `index.tsx` must keep the `"use client"` directive**
if it passes function props (event handlers, accessor callbacks, column
builders) down to a client child. Once `index.tsx` has no directive of its
own, Next.js treats it as a Server Component again, and passing functions
across that boundary fails at runtime with "Functions cannot be passed
directly to Client Components...". After splitting a client file, always
re-verify (`curl` the route / check the dev server log) rather than
assuming a refactor was purely mechanical.

## Routing

Dynamic routes follow `params: Promise<{ id: string }>`, `await params`,
then `notFound()` from `next/navigation` if the lookup misses — see
`app/[locale]/(noon)/(profile)/tickets/[id]/page.tsx`. Route `page.tsx`
files stay thin: fetch data (via the feature's `data.ts`/`api.ts`), then
render the feature's top-level component.
