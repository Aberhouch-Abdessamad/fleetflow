# FleetFlow design system

**Direction:** a calm, premium logistics console. The navy sidebar anchors the layout, content sits on a light neutral canvas with soft colour washes, blue is the primary action colour and teal is the accent for "live/moving" signals.

## Tokens (`src/index.css`)

Semantic CSS variables switch between light and dark, and Tailwind maps them through `@theme inline`:

| Token | Light | Dark | Utility |
| --- | --- | --- | --- |
| Canvas | `#f4f6fb` + radial washes | `#070c1c` | `bg-bg` |
| Surface / 2 / 3 | `#fff` / `#f8fafd` / `#eef2f8` | `#0d1529` / `#111b33` / `#16223f` | `bg-surface`, `bg-surface-2`, `bg-surface-3` |
| Text / soft / muted / subtle | `#0d1631` … `#97a1b8` | `#e8edf8` … `#5d6a8a` | `text-fg`, `text-fg-soft`, `text-muted`, `text-subtle` |
| Primary (blue) | `#2459e6` | `#4d7dff` | `bg-primary`, `text-primary`, `bg-primary-soft` |
| Accent (teal) | `#0fa89a` | `#2dd4bf` | `text-accent`, `bg-accent-soft` |
| Status | success / warning / danger / info + `-soft` | | `bg-success-soft text-success` … |
| Sidebar | navy `#0b1530` | `#060b19` | `bg-sidebar`, `text-sidebar-fg` |

- **Type:** Plus Jakarta Sans (display / headings), Inter (UI, with `cv11 ss01 ss03`).
- **Radius:** lg `0.75rem` for inputs and buttons, xl `1rem` for cards, 2xl for heroes and modals.
- **Shadows:** `shadow-xs`, `shadow-card`, `shadow-pop`, and `shadow-glow` (primary hover).
- **Motion:** `animate-fade-in`, `slide-up`, `scale-in`, `shimmer` (skeletons), `dash` (route art). `prefers-reduced-motion` is respected.

## Components (`src/components/ui`)

`Button` (primary, accent, secondary, outline, ghost, danger, link × sm, md, lg, icon; `loading`, `asChild`) · `Input` (leading and trailing slots) · `Select` · `Textarea` · `Field` (label, hint and error, wired to `aria-*`) · `Card`/`CardHeader`/`CardBody`/`CardFooter` · `Badge` + `StatusBadge` (one status→tone map for the whole app) · `Modal` + `ConfirmDialog` · `Dropdown*` · `Tooltip` · `Table`/`Th`/`Td`/`Tr` + `Pagination` · `Skeleton` · `EmptyState` · `Alert` · `Avatar` · `PageHeader` · `Spinner`.

## Rules

- Never show invented numbers. If data doesn't exist yet, render an `EmptyState` that explains what will appear and when.
- Use status colours only through `StatusBadge` so a status always looks the same everywhere.
- Every form uses `Field` + Zod + `applyServerErrors()` so backend 422s land on the right input.
